<?php

declare(strict_types=1);

use Domain\Supplier\Services\ClaudeClient;
use Domain\Supplier\Services\PatternParseService;

it('passes sections and pages from the study output through to the recipe rules', function () {
    $client = new class extends ClaudeClient
    {
        public array $lastSchema = [];

        protected function call(string $purpose, string $system, string $user, array $schema, ?string $model, int $maxTokens): array
        {
            $this->lastSchema = $schema;

            return [
                'feasible' => 'yes',
                'zones' => [['field' => 'wine_name', 'x_min' => '30', 'x_max' => '400']],
                'row_regex' => '',
                'require' => ['wine_name'],
                'carry' => [],
                'section_regex' => '',
                'section_field' => '',
                'sections' => [
                    ['regex' => '^SPIRITS$', 'set' => [], 'clears' => ['colour'], 'skip' => 'yes'],
                    ['regex' => '^(?<country>SPAIN)$', 'set' => [['field' => 'region', 'value' => 'Rioja']], 'clears' => [], 'skip' => 'no'],
                ],
                'pages' => ['min' => '3', 'max' => ''],
                'colour_map' => [],
                'format_unit' => '',
                'confidence' => 0.9,
                'notes' => 'test',
            ];
        }
    };

    $derived = $client->deriveRules('p1: [30]Sample');

    expect($derived['feasible'])->toBeTrue()
        ->and($derived['rules']['sections'])->toHaveCount(2)
        ->and($derived['rules']['pages'])->toBe(['min' => '3', 'max' => '']);

    // The stored (verbatim) rules sanitise into engine shape.
    $sane = (new PatternParseService)->sanitise($derived['rules']);
    expect($sane['sections'][0]['skip'])->toBeTrue()
        ->and($sane['sections'][1]['skip'])->toBeFalse()
        ->and($sane['sections'][1]['set'])->toBe(['region' => 'Rioja'])
        ->and($sane['pages'])->toBe(['min' => 3, 'max' => null]);

    // Structured-output constraints (live-API gotchas): no map objects and
    // every declared property required, recursively.
    $assertStrict = function (array $schema) use (&$assertStrict): void {
        if (($schema['type'] ?? '') === 'object') {
            expect($schema['additionalProperties'] ?? null)->toBeFalse()
                ->and(array_keys($schema['properties'] ?? []))->toBe($schema['required'] ?? null);
            foreach ($schema['properties'] as $property) {
                $assertStrict($property);
            }
        }
        if (($schema['type'] ?? '') === 'array' && isset($schema['items'])) {
            $assertStrict($schema['items']);
        }
    };
    $assertStrict($client->lastSchema);
});
