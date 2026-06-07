<?php

declare(strict_types=1);

namespace Domain\Import\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Caps how much of a spreadsheet PhpSpreadsheet loads into memory, applied
 * BEFORE ->load() so an oversized / decompression-bomb workbook can't exhaust
 * memory. Limits both rows and columns.
 */
class RowLimitReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $maxRows,
        private readonly int $maxColumns = 64,
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row > $this->maxRows) {
            return false;
        }

        // Column letters A..Z, then AA.. — compare against the Nth letter.
        return Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumns;
    }
}
