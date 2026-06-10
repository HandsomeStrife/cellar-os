<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Anthropic\Client;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Domain\Supplier\Exceptions\ResponseTruncatedException;
use RuntimeException;

/**
 * Thin wrapper over the Anthropic SDK for portfolio parsing. Three call shapes,
 * each pinned to a JSON schema via structured outputs so the model can only
 * return the shape we expect. This is the one place that talks to Claude; tests
 * bind a fake instance in its place, so no live API is hit under test.
 */
class ClaudeClient
{
    /** Product fields the parser maps onto (mirrors NormaliseService mapping keys). */
    public const FIELDS = [
        'wine_name', 'producer', 'country', 'region', 'sub_region',
        'grape', 'colour', 'vintage', 'format_ml', 'case_size', 'unit_price', 'stock',
    ];

    private ?Client $client = null;

    public function __construct(private ?string $apiKey = null, private ?string $defaultModel = null)
    {
        $this->apiKey ??= (string) config('services.anthropic.key');
        $this->defaultModel ??= (string) config('services.anthropic.model', 'claude-opus-4-8');
    }

    /**
     * Spreadsheet/CSV: derive a column mapping (productField => source header)
     * from the headers + a few sample rows. The mapping is the reusable recipe.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, string>>  $sampleRows
     * @return array{mapping: array<string, string>, confidence: float, notes: string}
     */
    public function deriveMapping(array $headers, array $sampleRows, ?string $model = null): array
    {
        $fields = implode(', ', self::FIELDS);

        $system = <<<SYS
            You map a wine trade price-list's columns onto CellarOS product fields.
            Target fields: {$fields}.
            - Return only fields that genuinely exist as a column; omit the rest.
            - `wine_name` is required; pick the column holding the wine/cuvée name.
            - Prefer the trade/ex-VAT unit price column for `unit_price`.
            - `format_ml` is the bottle size column (e.g. "750ml", "0.75", "Magnum").
            - Give a confidence 0..1 and a one-line note on anything ambiguous.
            SYS;

        $user = "Headers:\n".json_encode($headers)."\n\nSample rows:\n".json_encode(array_slice($sampleRows, 0, 12));

        // Structured outputs forbid free-form map objects (additionalProperties
        // must be false), so the mapping travels as {field, header} pairs.
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'mapping' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'field' => ['type' => 'string', 'enum' => self::FIELDS],
                            'header' => ['type' => 'string'],
                        ],
                        'required' => ['field', 'header'],
                    ],
                ],
                'confidence' => ['type' => 'number'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['mapping', 'confidence', 'notes'],
        ];

        $out = $this->call($system, $user, $schema, $model, 2000);

        $mapping = [];
        foreach (is_array($out['mapping'] ?? null) ? $out['mapping'] : [] as $pair) {
            if (is_array($pair) && isset($pair['field'], $pair['header'])) {
                $mapping[(string) $pair['field']] = (string) $pair['header'];
            }
        }

        return [
            'mapping' => $mapping,
            'confidence' => (float) ($out['confidence'] ?? 0),
            'notes' => (string) ($out['notes'] ?? ''),
        ];
    }

    /**
     * PDF: derive a structural recipe from a sample of the document — how the
     * list is laid out and where each field lives — reused to guide extraction.
     *
     * @return array{structure: string, notes: string, confidence: float}
     */
    public function deriveProfile(string $sampleText, ?string $model = null): array
    {
        $system = <<<'SYS'
            You are profiling a wine trade PDF price list so it can be parsed.
            Describe, concisely and concretely, how to read it: is it a table or
            prose? Do country/region/producer appear as cascading section headers
            that apply to the wines beneath them? Which part of a line holds the
            vintage, bottle size, and price (and if there are tiered prices, say to
            take the base/ex-VAT one)? Are grape varieties present? Note any quirks.
            Return a `structure` description a parser can follow, plus confidence.
            SYS;

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'structure' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['structure', 'notes', 'confidence'],
        ];

        $out = $this->call($system, "Sample (first pages):\n\n".$sampleText, $schema, $model, 2000);

        return [
            'structure' => (string) ($out['structure'] ?? ''),
            'notes' => (string) ($out['notes'] ?? ''),
            'confidence' => (float) ($out['confidence'] ?? 0),
        ];
    }

    /**
     * PDF: extract wine rows from one page-range chunk, guided by the recipe and
     * the section context carried from the previous chunk (so header-inherited
     * country/region/producer survive chunk boundaries).
     *
     * @param  array{structure?: string, notes?: string}  $recipe
     * @param  array<string, string>  $carrySection  last-seen country/region/producer
     * @return array{wines: array<int, array<string, mixed>>, section: array<string, string>}
     */
    public function extractWines(string $chunkText, array $recipe, array $carrySection, ?string $model = null): array
    {
        $fields = implode(', ', self::FIELDS);
        $structure = (string) ($recipe['structure'] ?? 'A wine trade price list.');
        $carry = json_encode($carrySection ?: (object) []);

        $system = <<<SYS
            You extract wines from a chunk of a wine trade price list into rows.
            How this list is structured: {$structure}

            Rules:
            - One row per distinct wine/vintage/format. Fields: {$fields}.
            - Inherit country/region/producer from the section headers above a wine.
            - The chunk may begin mid-section: the carried context below gives the
              country/region/producer in force at the start — apply it until a new
              header appears, and return the context in force at the END as `section`.
            - All values are strings; use "" (empty string) when a field is absent —
              never invent values. Use the base ex-VAT price for `unit_price`.
              Bottle sizes like "0.75" mean 750ml.
            - Skip headings, blurb, totals, and anything without a wine name.
            SYS;

        $examples = $recipe['examples'] ?? [];
        $exampleHint = is_array($examples) && $examples !== []
            ? "\n\nReviewer-approved examples from this supplier (match this shape):\n".json_encode(array_slice($examples, 0, 5))
            : '';

        $user = "Carried section context: {$carry}{$exampleHint}\n\nChunk text:\n\n".$chunkText;

        // Grammar-friendly schema: EVERY property required (optional properties
        // explode the compiled grammar — the API answers "Grammar compilation
        // timed out"). Absent values travel as '' and are nulled downstream by
        // NormaliseService.
        $wineSchema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array_combine(self::FIELDS, array_fill(0, count(self::FIELDS), ['type' => 'string'])),
            'required' => self::FIELDS,
        ];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'wines' => ['type' => 'array', 'items' => $wineSchema],
                'section' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'country' => ['type' => 'string'],
                        'region' => ['type' => 'string'],
                        'producer' => ['type' => 'string'],
                    ],
                    'required' => ['country', 'region', 'producer'],
                ],
            ],
            'required' => ['wines', 'section'],
        ];

        $out = $this->call($system, $user, $schema, $model, 16000);

        $section = array_filter([
            'country' => $out['section']['country'] ?? null,
            'region' => $out['section']['region'] ?? null,
            'producer' => $out['section']['producer'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'wines' => is_array($out['wines'] ?? null) ? $out['wines'] : [],
            'section' => $section,
        ];
    }

    /**
     * One structured-output call. Returns the decoded JSON object.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function call(string $system, string $user, array $schema, ?string $model, int $maxTokens): array
    {
        $message = $this->client()->messages->create(
            maxTokens: $maxTokens,
            messages: [['role' => 'user', 'content' => $user]],
            model: $model ?: $this->defaultModel,
            system: $system,
            outputConfig: OutputConfig::with(format: JSONOutputFormat::with(schema: $schema)),
        );

        if ($message->stopReason === 'max_tokens') {
            throw new ResponseTruncatedException('The response was cut off (chunk too dense).');
        }

        $text = $message->content[0]->text ?? '';
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Claude returned an unparseable response.');
        }

        return $decoded;
    }

    private function client(): Client
    {
        if ($this->apiKey === '' || $this->apiKey === null) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
