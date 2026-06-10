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

    /**
     * Coordinate-aware extraction for pattern parsing (pdftotext -bbox-layout).
     *
     * Poppler's <line> elements are the document's logical text runs — in a
     * tabular PDF each one is effectively a CELL, intact even when adjacent
     * columns visually overlap (which is what garbles plain layout text).
     * Cells are clustered into visual rows by y-proximity and sorted by x;
     * a cell's start-x identifies its column.
     *
     * @return array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>
     */
    public function pageRows(string $absolutePath, int $from, int $to): array
    {
        $process = new Process([
            'pdftotext', '-bbox-layout',
            '-f', (string) max(1, $from),
            '-l', (string) max($from, $to),
            $absolutePath, '-',
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to read the PDF (pdftotext -bbox-layout failed): '.trim($process->getErrorOutput()));
        }

        return $this->parseBboxXml($process->getOutput(), max(1, $from));
    }

    /**
     * @return array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>
     */
    public function parseBboxXml(string $xml, int $firstPage): array
    {
        $rows = [];
        $pageIndex = -1;
        $cells = [];
        $line = null;

        $flushPage = function () use (&$rows, &$cells, &$pageIndex, $firstPage): void {
            if ($cells === []) {
                return;
            }

            usort($cells, fn ($a, $b) => $a['y'] <=> $b['y'] ?: $a['x0'] <=> $b['x0']);

            $current = null;
            $pageRows = [];
            foreach ($cells as $cell) {
                if ($current === null || $cell['y'] - $current['y'] > 2.5) {
                    if ($current !== null) {
                        $pageRows[] = $current;
                    }
                    $current = ['page' => $firstPage + $pageIndex, 'y' => $cell['y'], 'cells' => []];
                }
                $current['cells'][] = ['text' => $cell['text'], 'x0' => $cell['x0'], 'x1' => $cell['x1']];
            }
            if ($current !== null) {
                $pageRows[] = $current;
            }

            foreach ($pageRows as $row) {
                usort($row['cells'], fn ($a, $b) => $a['x0'] <=> $b['x0']);
                $rows[] = $row;
            }

            $cells = [];
        };

        foreach (preg_split('/\n/', $xml) ?: [] as $raw) {
            if (str_contains($raw, '<page ')) {
                $flushPage();
                $pageIndex++;

                continue;
            }

            if (preg_match('/<line xMin="(-?[\d.]+)" yMin="(-?[\d.]+)" xMax="(-?[\d.]+)"/', $raw, $m)) {
                $line = ['x0' => (float) $m[1], 'y' => (float) $m[2], 'x1' => (float) $m[3], 'words' => []];

                continue;
            }

            if ($line !== null && preg_match('/<word [^>]*>(.*?)<\/word>/', $raw, $m)) {
                $line['words'][] = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);

                continue;
            }

            if ($line !== null && str_contains($raw, '</line>')) {
                if ($line['words'] !== []) {
                    $cells[] = [
                        'x0' => $line['x0'],
                        'y' => $line['y'],
                        'x1' => $line['x1'],
                        'text' => implode(' ', $line['words']),
                    ];
                }
                $line = null;
            }
        }
        $flushPage();

        return $rows;
    }
}
