<?php

declare(strict_types=1);

namespace Domain\Supplier\Exceptions;

use RuntimeException;

/**
 * Thrown by the stubbed DocumentAnalysisService until the real LLM extraction
 * pipeline is implemented. The analysis job catches this and records it as the
 * document's failure note, so the lifecycle is provably wired end-to-end.
 */
class AnalysisNotImplementedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('LLM analysis not yet implemented.');
    }
}
