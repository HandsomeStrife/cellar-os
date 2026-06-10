<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

/**
 * How a supplier document is parsed:
 *  - Tabular: spreadsheet/CSV — the recipe is a column mapping.
 *  - Document: PDF — the recipe is a structural description + few-shot examples.
 */
enum ParseMode: string
{
    case Tabular = 'tabular';
    case Document = 'document';

    /**
     * Derive the mode from a document's file extension first (unambiguous),
     * falling back to the mime type. A PDF named "price-sheet.pdf" must never
     * be mistaken for a spreadsheet.
     */
    public static function forFileType(?string $fileType, string $fileName): self
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return self::Document;
        }

        if (in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            return self::Tabular;
        }

        $mime = strtolower($fileType ?? '');

        if (str_contains($mime, 'pdf')) {
            return self::Document;
        }

        foreach (['csv', 'excel', 'spreadsheet', 'sheet', 'text/plain'] as $needle) {
            if (str_contains($mime, $needle)) {
                return self::Tabular;
            }
        }

        return self::Document;
    }
}
