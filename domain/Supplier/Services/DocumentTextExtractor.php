<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Domain\Import\Services\PriceListParser;
use RuntimeException;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;

/**
 * Turns an uploaded supplier file into text the parser can reason about:
 *  - spreadsheets/CSV → headers + associative rows (reuses PriceListParser).
 *  - PDFs → layout-preserving text, addressable by page range for chunking.
 *
 * Wraps poppler's pdftotext/pdfinfo (via spatie/pdf-to-text); the binaries ship
 * in the Sail image (poppler-utils).
 */
class DocumentTextExtractor
{
    public function __construct(private PriceListParser $parser = new PriceListParser) {}

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function tabular(string $absolutePath, string $extension): array
    {
        return $this->parser->parse($absolutePath, $extension);
    }

    public function pageCount(string $absolutePath): int
    {
        $process = new Process(['pdfinfo', $absolutePath]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to read the PDF (pdfinfo failed): '.trim($process->getErrorOutput()));
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $m)) {
            return (int) $m[1];
        }

        return 1;
    }

    /**
     * Layout-preserving text for pages [$from, $to] (1-indexed, inclusive).
     */
    public function pageText(string $absolutePath, int $from, int $to): string
    {
        return Pdf::getText($absolutePath, null, [
            'layout',
            'f '.max(1, $from),
            'l '.max($from, $to),
        ], timeout: 120);
    }
}
