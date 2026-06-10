<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Supplier\Services\DocumentTextExtractor;

/**
 * Test double for DocumentTextExtractor — fakes PDF page counts/text so tests
 * need no real PDF or poppler binary. (Tabular still delegates to the real
 * PriceListParser against a faked-disk CSV.)
 */
class FakeDocumentTextExtractor extends DocumentTextExtractor
{
    /**
     * @param  array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>  $rows
     */
    public function __construct(public int $pages = 2, public string $text = 'fake page text', public array $rows = []) {}

    public function pageCount(string $absolutePath): int
    {
        return $this->pages;
    }

    public function pageText(string $absolutePath, int $from, int $to): string
    {
        return $this->text;
    }

    public function pageRows(string $absolutePath, int $from, int $to): array
    {
        // Serve only the requested page range, like the real extractor.
        return array_values(array_filter(
            $this->rows,
            fn (array $row) => $row['page'] >= $from && $row['page'] <= $to,
        ));
    }
}
