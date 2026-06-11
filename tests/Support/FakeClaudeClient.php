<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Supplier\Services\ClaudeClient;
use RuntimeException;

/**
 * Test double for ClaudeClient — returns canned structured output so no live API
 * is hit. Configure the canned mapping / wines per test.
 */
class FakeClaudeClient extends ClaudeClient
{
    public int $deriveMappingCalls = 0;

    public int $extractCalls = 0;

    /**
     * @param  array<string, string>  $mapping
     * @param  array<int, array<string, mixed>>  $wines
     * @param  array<string, string>  $section
     */
    public function __construct(
        public array $mapping = [],
        public array $wines = [],
        public array $section = [],
        public float $confidence = 0.9,
        public bool $failIfMappingDerived = false,
    ) {
        // Skip parent config lookup; this client never talks to the API.
    }

    public function deriveMapping(array $headers, array $sampleRows, ?string $model = null): array
    {
        $this->deriveMappingCalls++;

        if ($this->failIfMappingDerived) {
            throw new RuntimeException('deriveMapping should not have been called (recipe should have been reused).');
        }

        return ['mapping' => $this->mapping, 'confidence' => $this->confidence, 'notes' => 'fake mapping'];
    }

    public function deriveProfile(string $sampleText, ?string $model = null): array
    {
        return ['structure' => 'fake structure', 'notes' => 'fake', 'confidence' => $this->confidence];
    }

    /** @var array<string, mixed> canned machine rules; empty = not feasible (LLM path) */
    public array $rules = [];

    public int $deriveRulesCalls = 0;

    public function deriveRules(string $renderedRows, ?string $model = null): array
    {
        $this->deriveRulesCalls++;

        return [
            'feasible' => $this->rules !== [],
            'rules' => $this->rules,
            'confidence' => $this->confidence,
            'notes' => 'fake rules',
        ];
    }

    public function extractWines(string $chunkText, array $recipe, array $carrySection, ?string $model = null): array
    {
        $this->extractCalls++;

        return ['wines' => $this->wines, 'section' => $this->section];
    }

    /** @var array<string, string> canned LWIN picks: item index => lwin */
    public array $lwinPicks = [];

    public function pickLwins(array $items, ?string $model = null): array
    {
        return $this->lwinPicks;
    }
}
