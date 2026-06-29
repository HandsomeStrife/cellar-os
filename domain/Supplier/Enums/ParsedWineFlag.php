<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

/**
 * Safety flags raised on a parsed wine for the human review queue. Stored as a
 * plain string on `parsed_wines.flag` (values are stable — they ride existing
 * data); this enum gives them labels/colours and keeps the vocabulary in one
 * place.
 */
enum ParsedWineFlag: string
{
    case SuspiciousPrice = 'suspicious_price';
    case SuspectedHeading = 'suspected_heading';
    case MissingPrice = 'missing_price';
    case LowConfidence = 'low_confidence';
    case AmbiguousPricing = 'ambiguous_pricing';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuspiciousPrice => 'Suspicious price',
            self::SuspectedHeading => 'Suspected heading',
            self::MissingPrice => 'Missing price',
            self::LowConfidence => 'Low confidence',
            self::AmbiguousPricing => 'Check case vs bottle',
        };
    }

    /**
     * Badge colour (maps to the x-badge palette).
     */
    public function getColour(): string
    {
        return match ($this) {
            self::SuspiciousPrice, self::MissingPrice => 'red',
            self::SuspectedHeading => 'gray',
            self::LowConfidence => 'amber',
            self::AmbiguousPricing => 'amber',
        };
    }
}
