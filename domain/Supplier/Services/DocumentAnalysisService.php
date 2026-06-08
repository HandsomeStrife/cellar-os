<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Exceptions\AnalysisNotImplementedException;

/**
 * The single integration boundary for LLM-powered portfolio analysis.
 *
 * This is the ONLY place the real extraction work plugs in later. The intended
 * implementation:
 *
 *   1. Read the original file from `$document->storage_path` (private disk).
 *   2. Send it to an LLM with a structured extraction prompt.
 *   3. Run safety checks (row/column sanity, price/vintage validation,
 *      hallucination guards, confidence thresholds).
 *   4. Return normalised rows in CellarOS's standardised catalogue shape
 *      (mirroring what Import\Services\NormaliseService produces) for review.
 *
 * Until then it throws, and AnalyseSupplierDocumentJob records the failure so
 * the upload -> analyse -> status lifecycle is exercised today.
 *
 * @phpstan-return array<int, array<string, mixed>>
 */
class DocumentAnalysisService
{
    /**
     * @return array<int, array<string, mixed>> normalised catalogue rows
     */
    public function analyse(SupplierDocumentData $document): array
    {
        throw AnalysisNotImplementedException::make();
    }
}
