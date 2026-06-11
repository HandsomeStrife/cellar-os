<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Support\WineIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

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
     * xlsx in chunked passes (PhpSpreadsheet holds whole loads in memory, so
     * a 200k-row file is read 10k rows at a time).
     *
     * @return \Generator<int, array<string, string>>
     */
    private function xlsxRows(string $path): \Generator
    {
        $chunk = 10000;
        $start = 1;
        $map = null;

        while (true) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class($start, $chunk) implements IReadFilter
            {
                public function __construct(private int $start, private int $chunk) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row === 1 || ($row >= $this->start && $row < $this->start + $this->chunk);
                }
            });

            $sheet = $reader->load($path)->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);

            if ($map === null) {
                $map = $this->headerMap(array_map(fn ($v) => (string) ($v ?? ''), $rows[0] ?? []));
            }

            $yielded = 0;
            foreach ($rows as $i => $line) {
                $absolute = $i + 1; // toArray is 0-indexed from row 1 due to the filter including row 1
                if ($absolute === 1 || $line === null) {
                    continue;
                }
                $line = array_map(fn ($v) => (string) ($v ?? ''), $line);
                if (implode('', $line) === '') {
                    continue;
                }
                yield $this->mapRow($line, $map);
                $yielded++;
            }

            if ($yielded === 0) {
                break;
            }

            $start = $start === 1 ? 1 + $chunk : $start + $chunk;
        }
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
