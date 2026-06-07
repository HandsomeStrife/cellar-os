<?php

declare(strict_types=1);

namespace Domain\Import\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads an uploaded price-list file into headers + associative rows.
 * CSV is parsed natively; xlsx/xls via PhpSpreadsheet.
 */
class PriceListParser
{
    public const MAX_ROWS = 5000;

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function parse(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        $matrix = in_array($extension, ['csv', 'txt'], true)
            ? $this->readCsv($path)
            : $this->readSpreadsheet($path);

        return $this->toAssociative($matrix);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = array_map(fn ($v) => (string) ($v ?? ''), $data);

            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readSpreadsheet(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            // Cap rows/columns BEFORE load() to bound memory on huge/bomb files.
            $reader->setReadFilter(new RowLimitReadFilter(self::MAX_ROWS + 1));
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to read that spreadsheet file.', 0, $e);
        }

        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $rows[] = array_map(fn ($v) => (string) ($v ?? ''), $row);
        }

        return $rows;
    }

    /**
     * First non-empty row becomes the headers; remaining rows are keyed by header.
     *
     * @param  array<int, array<int, string>>  $matrix
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    private function toAssociative(array $matrix): array
    {
        $matrix = array_values(array_filter($matrix, fn ($row) => implode('', $row) !== ''));

        if ($matrix === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($matrix);
        $headers = [];

        foreach ($headerRow as $i => $header) {
            $header = trim($header);
            $headers[] = $header !== '' ? $header : 'Column '.($i + 1);
        }

        $rows = [];
        foreach ($matrix as $line) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $rows[] = $assoc;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
