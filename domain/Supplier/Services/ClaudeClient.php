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

    /** USD per MILLION tokens [input, output] per supported model. */
    public const PRICES = [
        'claude-opus-4-8' => [5.0, 25.0],
        'claude-sonnet-4-6' => [3.0, 15.0],
        'claude-haiku-4-5' => [1.0, 5.0],
    ];

    /** @var array{input: int, output: int} tokens accumulated across this instance's calls */
    private array $usage = ['input' => 0, 'output' => 0];

    /** @var array{input: int, output: int}|null tokens of the most recent call */
    public ?array $last_usage = null;

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
     * PDF: study a sample of coordinate-extracted rows ONCE and write the
     * machine rules (zones / regex / maps) that parse this document for free.
     * The rendered sample shows each cell as [startX]text so zone boundaries
     * can be proposed. Returns feasible=false when the layout defeats
     * deterministic parsing (the caller falls back to LLM extraction).
     *
     * @return array{feasible: bool, rules: array<string, mixed>, confidence: float, notes: string}
     */
    public function deriveRules(string $renderedRows, ?string $model = null): array
    {
        $fields = implode(', ', self::FIELDS);

        $system = <<<SYS
            You are studying a wine trade price list extracted from a PDF as rows of
            cells, each cell prefixed with its [startX] coordinate. Write MACHINE RULES
            that a deterministic parser will execute on every row:

            - zones: assign a column to a field by its start-x range (x_min inclusive,
              x_max exclusive). Use field "ignore" for narrative/noise columns.
              Target fields: {$fields}.
            - row_regex: optional PCRE body (NO delimiters) with named groups
              (?<field>...) from the target fields, matched against the row's
              non-ignored text joined by spaces. Captures override zone values.
            - require: fields that must be non-empty for a row to count as a wine
              (e.g. wine_name + unit_price for a priced table, wine_name + format_ml
              for a stock list).
            - carry: fields whose value carries down from previous rows when blank
              (country/region/producer printed once per group).
            - section_regex: optional PCRE body with a named group (?<value>...) that
              identifies section-header rows (e.g. ^[A-Z' ]+:$); section_field is
              where the current section value lands.
            - colour_map: style shorthands → one of: Red, White, Rosé, Orange,
              Sparkling, Dessert, Fortified (e.g. r→Red, w→White, sp→Sparkling).
            - format_unit: unit for bare numeric bottle sizes ("75" meaning 75cl → "cl").

            Set feasible="no" if columns are too garbled/interleaved for reliable
            deterministic parsing — be honest; a wrong "yes" silently corrupts data.
            CRITICAL test: mentally concatenate the cells that would fall in your
            wine_name zone for a few sample rows. If that text would mix in words
            from OTHER columns (producer, region, vintage, grape interleaving with
            the name, e.g. "2024 PLAIMONT, LE wine FRANCE"), the wine names are
            corrupted and you MUST answer feasible="no" — clean tail columns
            (price/size/abv) alone do not make a document feasible.
            Use "" or [] for anything unused. All numbers as strings.
            SYS;

        $zoneFields = array_merge(self::FIELDS, ['ignore']);
        $colours = ['Red', 'White', 'Rosé', 'Orange', 'Sparkling', 'Dessert', 'Fortified'];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'feasible' => ['type' => 'string', 'enum' => ['yes', 'no']],
                'zones' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'field' => ['type' => 'string', 'enum' => $zoneFields],
                            'x_min' => ['type' => 'string'],
                            'x_max' => ['type' => 'string'],
                        ],
                        'required' => ['field', 'x_min', 'x_max'],
                    ],
                ],
                'row_regex' => ['type' => 'string'],
                'require' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::FIELDS]],
                'carry' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::FIELDS]],
                'section_regex' => ['type' => 'string'],
                'section_field' => ['type' => 'string'],
                'colour_map' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'colour' => ['type' => 'string', 'enum' => $colours],
                        ],
                        'required' => ['code', 'colour'],
                    ],
                ],
                'format_unit' => ['type' => 'string', 'enum' => ['cl', 'ml', 'l', '']],
                'confidence' => ['type' => 'number'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['feasible', 'zones', 'row_regex', 'require', 'carry', 'section_regex', 'section_field', 'colour_map', 'format_unit', 'confidence', 'notes'],
        ];

        $out = $this->call($system, "Sample rows:\n\n".$renderedRows, $schema, $model, 4000);

        return [
            'feasible' => ($out['feasible'] ?? 'no') === 'yes',
            'rules' => [
                'zones' => $out['zones'] ?? [],
                'row_regex' => (string) ($out['row_regex'] ?? ''),
                'require' => $out['require'] ?? [],
                'carry' => $out['carry'] ?? [],
                'section_regex' => (string) ($out['section_regex'] ?? ''),
                'section_field' => (string) ($out['section_field'] ?? ''),
                'colour_map' => $out['colour_map'] ?? [],
                'format_unit' => (string) ($out['format_unit'] ?? ''),
            ],
            'confidence' => (float) ($out['confidence'] ?? 0),
            'notes' => (string) ($out['notes'] ?? ''),
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

        $this->last_usage = ['input' => $message->usage->inputTokens, 'output' => $message->usage->outputTokens];
        $this->usage['input'] += $message->usage->inputTokens;
        $this->usage['output'] += $message->usage->outputTokens;

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

    /**
     * Tokens spent across this instance's calls (input/output).
     *
     * @return array{input: int, output: int}
     */
    public function usageTotals(): array
    {
        return $this->usage;
    }

    /**
     * Cost in USD of the accumulated usage at a model's prices.
     */
    public function usageCost(?string $model = null): float
    {
        [$in, $out] = self::PRICES[$model ?: $this->defaultModel] ?? self::PRICES['claude-opus-4-8'];

        return ($this->usage['input'] / 1_000_000) * $in + ($this->usage['output'] / 1_000_000) * $out;
    }

    /**
     * Exact input-token count for a text (the count-tokens endpoint is free).
     */
    public function countTokens(string $text, ?string $model = null): int
    {
        return $this->client()->messages->countTokens(
            messages: [['role' => 'user', 'content' => $text]],
            model: $model ?: $this->defaultModel,
        )->inputTokens;
    }

    private function client(): Client
    {
        if ($this->apiKey === '' || $this->apiKey === null) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
