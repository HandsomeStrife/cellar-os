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
    public function __construct(public int $pages = 2, public string $text = 'fake page text') {}

    public function pageCount(string $absolutePath): int
    {
        return $this->pages;
    }

    public function pageText(string $absolutePath, int $from, int $to): string
    {
        return $this->text;
    }
}
