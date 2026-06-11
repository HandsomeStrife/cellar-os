<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Support\WineIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Imports/refreshes the Liv-ex LWIN reference database from the published file
 * (CSV preferred; xlsx supported via chunked reads). Keep the source file on
 * the private disk (lwin/ directory) so resets re-import without
 * re-downloading. Header-driven: column order in the file doesn't matter.
 * Idempotent upsert keyed on the LWIN code.
 */
class RefreshLwinDatabase extends Command
{
    private const BATCH = 1000;

    /** LWIN file header (normalised) => lwins column. */
    private const COLUMNS = [
        'lwin' => 'lwin',
        'status' => 'status',
        'display_name' => 'display_name',
        'producer_title' => 'producer_title',
        'producer_name' => 'producer_name',
        'wine' => 'wine',
        'country' => 'country',
        'region' => 'region',
        'sub_region' => 'sub_region',
        'site' => 'site',
        'parcel' => 'parcel',
        'colour' => 'colour',
        'color' => 'colour',
        'type' => 'type',
        'sub_type' => 'sub_type',
        'designation' => 'designation',
        'classification' => 'classification',
        'first_vintage' => 'first_vintage',
        'final_vintage' => 'final_vintage',
        'reference' => 'reference',
    ];

    protected $signature = 'wine:lwin-refresh {path=lwin/lwin-database.csv : file on the private disk (csv or xlsx)}';

    protected $description = 'Import/refresh the Liv-ex LWIN reference database.';

    public function handle(): int
    {
        $relative = (string) $this->argument('path');
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            $this->error("No LWIN file at {$disk->path($relative)} — download it from liv-ex.com/lwin and place it there.");

            return self::FAILURE;
        }

        $path = $disk->path($relative);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        $count = 0;
        $skipped = 0;
        $batch = [];

        foreach ($this->rows($path, $extension) as $row) {
            // Tolerate spreadsheet float formatting ("1000001.0").
            $lwin = preg_replace('/\.0+$/', '', trim((string) ($row['lwin'] ?? ''))) ?? '';

            // LWIN7 only — vintage/size-extended codes derive from it.
            if (! preg_match('/^\d{7}$/', $lwin)) {
                $skipped++;

                continue;
            }

            $record = ['lwin' => $lwin];
            foreach (array_unique(array_values(self::COLUMNS)) as $column) {
                if ($column === 'lwin') {
                    continue;
                }
                $value = trim((string) ($row[$column] ?? ''));
                // The published file uses literal "NA" for absent values.
                $record[$column] = ($value === '' || strcasecmp($value, 'NA') === 0) ? null : mb_substr($value, 0, 250);
            }

            $record['identity_key'] = WineIdentity::keyFor($record['producer_name'], $record['wine']);
            $record['name_key'] = WineIdentity::normalise($record['display_name']) ?: null;
            $record['created_at'] = now();
            $record['updated_at'] = now();

            $batch[] = $record;
            $count++;

            if (count($batch) >= self::BATCH) {
                $this->flush($batch);
                $batch = [];

                if ($count % 20000 === 0) {
                    $this->line(number_format($count).'…');
                }
            }
        }

        $this->flush($batch);

        $this->info(sprintf('LWIN refresh complete: %s rows imported/updated, %s skipped; table now holds %s.', number_format($count), number_format($skipped), number_format(Lwin::count())));
        $this->line('Licence: Liv-ex LWIN database, Creative Commons — keep the attribution note in docs.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flush(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        Lwin::upsert(
            $batch,
            ['lwin'],
            ['status', 'display_name', 'producer_title', 'producer_name', 'wine', 'country', 'region', 'sub_region', 'site', 'parcel', 'colour', 'type', 'sub_type', 'designation', 'classification', 'first_vintage', 'final_vintage', 'reference', 'identity_key', 'name_key', 'updated_at'],
        );
    }

    /**
     * Stream rows as [column => value] keyed by our normalised column names.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function rows(string $path, string $extension): \Generator
    {
        if ($extension === 'csv' || $extension === 'txt') {
            yield from $this->csvRows($path);

            return;
        }

        yield from $this->xlsxRows($path);
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return;
        }

        $map = null;

        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($map === null) {
                $map = $this->headerMap($line);

                continue;
            }

            yield $this->mapRow($line, $map);
        }

        fclose($handle);
    }

    /**
     * Streams xlsx rows via XMLReader (ZipArchive + sheet XML) — PhpSpreadsheet
     * would need the whole 200k-row sheet in memory or repeated full parses.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function xlsxRows(string $path): \Generator
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return;
        }

        // Shared strings (cell values of type s reference this table).
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $reader = new \XMLReader;
            $reader->XML($sharedXml);
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'si') {
                    $node = simplexml_load_string($reader->readOuterXml());
                    $text = '';
                    foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $t) {
                        $text .= (string) $t;
                    }
                    $shared[] = $text;
                }
            }
            $reader->close();
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return;
        }

        $reader = new \XMLReader;
        $reader->XML($sheetXml);
        $map = null;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $row = simplexml_load_string($reader->readOuterXml());
            $line = [];

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $index = $this->columnIndex(preg_replace('/\d+/', '', $ref) ?? '');
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $line[$index] = $value;
            }

            if ($line === []) {
                continue;
            }

            // Re-key into a dense 0..n array (missing cells become '').
            $dense = array_fill(0, max(array_keys($line)) + 1, '');
            foreach ($line as $i => $v) {
                $dense[$i] = $v;
            }

            if ($map === null) {
                $map = $this->headerMap($dense);

                continue;
            }

            yield $this->mapRow($dense, $map);
        }

        $reader->close();
    }

    /** Column letters ("A", "AB") to a zero-based index. */
    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, string|null>  $headerLine
     * @return array<int, string> column index => lwins column
     */
    private function headerMap(array $headerLine): array
    {
        $map = [];

        foreach ($headerLine as $i => $header) {
            $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $header) ?? '', '_'));

            if (isset(self::COLUMNS[$key])) {
                $map[$i] = self::COLUMNS[$key];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string|null>  $line
     * @param  array<int, string>  $map
     * @return array<string, string>
     */
    private function mapRow(array $line, array $map): array
    {
        $row = [];

        foreach ($map as $i => $column) {
            $row[$column] = (string) ($line[$i] ?? '');
        }

        return $row;
    }
}
