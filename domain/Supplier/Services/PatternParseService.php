<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Domain\Catalogue\Enums\WineColour;

/**
 * Executes a machine "rules" recipe over coordinate-extracted rows — the cheap
 * deterministic half of the hybrid parser. The rules are written ONCE by the
 * LLM studying the document (zones by cell start-x, an optional row regex,
 * carry-down fields, section headers, code maps); running them costs nothing,
 * so re-uploads of a studied supplier's list parse for free.
 *
 * Rules shape (all keys optional unless noted):
 *   zones:          [{field, x_min, x_max}]  field ∈ ClaudeClient::FIELDS ∪ {ignore}
 *   row_regex:      PCRE body (no delimiters) with named groups ∈ FIELDS,
 *                   matched against the row's non-ignored text; captures
 *                   override zone values
 *   require:        fields that must be non-empty for a wine row (default [wine_name])
 *   carry:          fields inheriting the last non-empty value from rows above
 *   section_regex:  PCRE body with a named group `value`; a matching row is a
 *                   section header, not a wine
 *   section_field:  where the current section value lands (e.g. region)
 *   sections:       [{regex, set?, clears?, skip?}] — multi-level header rules
 *                   for documents whose headers nest (type page → country →
 *                   region). A row matching `regex` is a header, not a wine:
 *                   `set` assigns literal field values ({colour: "Sparkling"}),
 *                   named groups in the regex capture dynamic ones
 *                   ((?<country>…)), and `clears` drops now-stale narrower
 *                   context. Every later wine row inherits the accumulated
 *                   values into its empty fields. Header text is matched both
 *                   raw and with drop-cap letter-spacing folded ("S PAIN" →
 *                   "SPAIN"); all-caps captures are title-cased.
 *                   `skip: true` marks a NON-WINE section (spirits, beer,
 *                   sake…): every row after it is discarded until a section
 *                   rule without skip matches.
 *   pages:          {min?, max?} — 1-based inclusive page window; rows outside
 *                   it are ignored entirely (front matter, spirits back pages).
 *   colour_map:     {code => WineColour value} for style shorthands (r, w, sp…)
 *   format_unit:    appended to a bare numeric format_ml (e.g. "cl" → "75cl")
 */
class PatternParseService
{
    /**
     * @param  array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>  $rows
     * @param  array<string, mixed>  $rules
     * @param  array{section?: string, section_values?: array<string, string>, carry?: array<string, string>, skipping?: bool}  $state  carry/section context threaded across page batches
     * @return array{rows: array<int, array<string, string>>, matched: int, residue: int, state: array{section: string, section_values: array<string, string>, carry: array<string, string>, skipping: bool}}
     */
    public function parse(array $rows, array $rules, array $state = []): array
    {
        $rules = $this->sanitise($rules);
        $require = $rules['require'] !== [] ? $rules['require'] : ['wine_name'];

        $wines = [];
        $residue = 0;
        // Context survives batch boundaries — a producer/section printed on
        // page 50 still applies to page 51's rows.
        $section = (string) ($state['section'] ?? '');
        $sectionValues = (array) ($state['section_values'] ?? []);
        $carryValues = (array) ($state['carry'] ?? []);
        $skipping = (bool) ($state['skipping'] ?? false);

        foreach ($rows as $row) {
            // Pages outside the recipe's window (front matter, non-wine back
            // pages) are not part of the list at all.
            if (($rules['pages']['min'] !== null && $row['page'] < $rules['pages']['min'])
                || ($rules['pages']['max'] !== null && $row['page'] > $rules['pages']['max'])) {
                continue;
            }
            $fields = [];
            $textParts = [];

            foreach ($row['cells'] as $cell) {
                $zone = $this->zoneFor($cell['x0'], $rules['zones']);

                if ($zone === 'ignore') {
                    continue;
                }

                $textParts[] = $cell['text'];

                if ($zone !== null) {
                    $fields[$zone] = trim(($fields[$zone] ?? '').' '.$cell['text']);
                }
            }

            $text = trim(implode(' ', $textParts));

            if ($text === '') {
                continue;
            }

            // Section headers update context and are never wines. Multi-level
            // rules run first: each accumulates its own fields so a type
            // header and a country header both shape the same wine row.
            $matched = $this->matchSections($text, $rules['sections'], $sectionValues);
            if ($matched !== null) {
                // A skip section (spirits, beer…) suspends wine collection; any
                // ordinary section header resumes it.
                $skipping = $matched['skip'];

                continue;
            }

            if ($skipping) {
                continue;
            }

            if ($rules['section_regex'] !== '' && preg_match(self::wrap($rules['section_regex']), $text, $m)) {
                $section = trim($m['value'] ?? $text);

                continue;
            }

            // Regex captures override zone values.
            if ($rules['row_regex'] !== '' && preg_match(self::wrap($rules['row_regex']), $text, $m)) {
                foreach (ClaudeClient::FIELDS as $field) {
                    if (isset($m[$field]) && trim($m[$field]) !== '') {
                        $fields[$field] = trim($m[$field]);
                    }
                }
            }

            $fields = $this->transform($fields, $rules);

            if ($rules['section_field'] !== '' && $section !== '' && trim($fields[$rules['section_field']] ?? '') === '') {
                $fields[$rules['section_field']] = $section;
            }

            foreach ($sectionValues as $field => $value) {
                if (trim($fields[$field] ?? '') === '') {
                    $fields[$field] = $value;
                }
            }

            // Carry-down: remember fresh values, fill gaps from above. A row
            // that is only a producer/region cell still feeds the memory.
            foreach ($rules['carry'] as $field) {
                $value = trim($fields[$field] ?? '');

                if ($value !== '') {
                    $carryValues[$field] = $value;
                } elseif (isset($carryValues[$field])) {
                    $fields[$field] = $carryValues[$field];
                }
            }

            $isWine = true;
            foreach ($require as $field) {
                if (trim($fields[$field] ?? '') === '') {
                    $isWine = false;
                    break;
                }
            }

            if ($isWine) {
                $fields['_page'] = (string) $row['page'];
                $wines[] = $fields;
            } elseif (count($row['cells']) >= 2) {
                $residue++;
            }
        }

        return [
            'rows' => $wines,
            'matched' => count($wines),
            'residue' => $residue,
            'state' => ['section' => $section, 'section_values' => $sectionValues, 'carry' => $carryValues, 'skipping' => $skipping],
        ];
    }

    /**
     * Try each multi-level section rule against the row text (raw, then with
     * drop-cap letter-spacing folded). On a match the row is a header: static
     * `set` values and named-group captures update the running context,
     * `clears` drops fields the new section invalidates. Returns the matched
     * rule (so the caller can honour its `skip`), or null.
     *
     * @param  array<int, array{regex: string, set: array<string, string>, clears: array<int, string>, skip: bool}>  $sectionRules
     * @param  array<string, string>  $sectionValues
     * @return array{regex: string, set: array<string, string>, clears: array<int, string>, skip: bool}|null
     */
    private function matchSections(string $text, array $sectionRules, array &$sectionValues): ?array
    {
        if ($sectionRules === []) {
            return null;
        }

        $candidates = [$text];
        $folded = self::foldDropCaps($text);
        if ($folded !== $text) {
            $candidates[] = $folded;
        }

        foreach ($sectionRules as $rule) {
            foreach ($candidates as $candidate) {
                if (! preg_match(self::wrap($rule['regex']), $candidate, $m)) {
                    continue;
                }

                foreach ($rule['clears'] as $field) {
                    unset($sectionValues[$field]);
                }
                foreach ($rule['set'] as $field => $value) {
                    $sectionValues[$field] = $value;
                }
                foreach (ClaudeClient::FIELDS as $field) {
                    if (isset($m[$field]) && trim($m[$field]) !== '') {
                        $sectionValues[$field] = self::tidyHeaderValue(trim($m[$field]));
                    }
                }

                return $rule;
            }
        }

        return null;
    }

    /**
     * Collapse drop-cap letter spacing ("S PARKLING W INES" → "SPARKLING
     * WINES") so section regexes can be written against readable text.
     */
    private static function foldDropCaps(string $text): string
    {
        return preg_replace('/(?<=^|\s)(\p{Lu}) (?=\p{Lu})/u', '$1', $text) ?? $text;
    }

    /**
     * ALL-CAPS header captures read badly as data ("RIOJA") — title-case them,
     * keeping joining words lowercase ("United States of America").
     */
    private static function tidyHeaderValue(string $value): string
    {
        if (mb_strtoupper($value) !== $value) {
            return $value;
        }

        $words = explode(' ', mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8'));
        foreach ($words as $i => $word) {
            if ($i > 0 && in_array(mb_strtolower($word), ['of', 'and', 'de', 'du', 'des', 'la', 'le', 'les', 'the'], true)) {
                $words[$i] = mb_strtolower($word);
            }
        }

        return implode(' ', $words);
    }

    /**
     * Render coordinate rows for the LLM's one-off study: each cell prefixed
     * with its start-x so zone boundaries can be proposed.
     *
     * @param  array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>  $rows
     */
    public function renderForStudy(array $rows, int $limit = 120): string
    {
        $lines = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $cells = array_map(
                fn (array $cell) => '['.round($cell['x0']).']'.$cell['text'],
                $row['cells'],
            );
            $lines[] = 'p'.$row['page'].': '.implode(' || ', $cells);
        }

        return implode("\n", $lines);
    }

    /**
     * Validate/normalise LLM-written rules so nothing unsafe or broken executes.
     *
     * @param  array<string, mixed>  $rules
     * @return array{zones: array<int, array{field: string, x_min: float, x_max: float}>, row_regex: string, require: array<int, string>, carry: array<int, string>, section_regex: string, section_field: string, sections: array<int, array{regex: string, set: array<string, string>, clears: array<int, string>, skip: bool}>, pages: array{min: int|null, max: int|null}, colour_map: array<string, string>, format_unit: string}
     */
    public function sanitise(array $rules): array
    {
        $fields = ClaudeClient::FIELDS;

        $zones = [];
        foreach ((array) ($rules['zones'] ?? []) as $zone) {
            $field = (string) ($zone['field'] ?? '');
            if (($field === 'ignore' || in_array($field, $fields, true)) && is_numeric($zone['x_min'] ?? null) && is_numeric($zone['x_max'] ?? null)) {
                $zones[] = ['field' => $field, 'x_min' => (float) $zone['x_min'], 'x_max' => (float) $zone['x_max']];
            }
        }

        $colours = array_column(WineColour::cases(), 'value');
        $colourMap = [];
        foreach ((array) ($rules['colour_map'] ?? []) as $code => $colour) {
            // Accept both {code: colour} and the LLM's [{code, colour}] pair shape.
            if (is_array($colour)) {
                $code = (string) ($colour['code'] ?? '');
                $colour = (string) ($colour['colour'] ?? '');
            }
            if ($code !== '' && in_array($colour, $colours, true)) {
                $colourMap[mb_strtolower(trim((string) $code))] = $colour;
            }
        }

        $sections = [];
        foreach ((array) ($rules['sections'] ?? []) as $rule) {
            $regex = $this->validRegex((string) ($rule['regex'] ?? ''));
            if ($regex === '') {
                continue;
            }

            $set = [];
            foreach ((array) ($rule['set'] ?? []) as $field => $value) {
                // Accept both {field: value} and the LLM's [{field, value}]
                // pair shape (structured outputs cannot express maps).
                if (is_array($value)) {
                    $field = (string) ($value['field'] ?? '');
                    $value = $value['value'] ?? '';
                }
                if (in_array($field, $fields, true) && is_scalar($value) && trim((string) $value) !== '') {
                    $set[$field] = trim((string) $value);
                }
            }

            $sections[] = [
                'regex' => $regex,
                'set' => $set,
                'clears' => array_values(array_intersect((array) ($rule['clears'] ?? []), $fields)),
                // Accept real booleans and the LLM's "yes"/"no" strings.
                'skip' => filter_var($rule['skip'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $pages = (array) ($rules['pages'] ?? []);

        return [
            'zones' => $zones,
            'row_regex' => $this->validRegex((string) ($rules['row_regex'] ?? '')),
            'require' => array_values(array_intersect((array) ($rules['require'] ?? []), $fields)),
            'carry' => array_values(array_intersect((array) ($rules['carry'] ?? []), $fields)),
            'section_regex' => $this->validRegex((string) ($rules['section_regex'] ?? '')),
            'section_field' => in_array($rules['section_field'] ?? '', $fields, true) ? (string) $rules['section_field'] : '',
            'sections' => array_slice($sections, 0, 60),
            'pages' => [
                'min' => is_numeric($pages['min'] ?? null) ? (int) $pages['min'] : null,
                'max' => is_numeric($pages['max'] ?? null) ? (int) $pages['max'] : null,
            ],
            'colour_map' => $colourMap,
            'format_unit' => in_array($rules['format_unit'] ?? '', ['cl', 'ml', 'l'], true) ? (string) $rules['format_unit'] : '',
        ];
    }

    /**
     * Zones are matched on the ROUNDED start-x — renderForStudy shows the model
     * rounded coordinates, so boundaries must be compared the same way (a cell
     * at x0=463.66 displayed as [464] belongs to a zone starting at 464).
     *
     * @param  array<int, array{field: string, x_min: float, x_max: float}>  $zones
     */
    private function zoneFor(float $x0, array $zones): ?string
    {
        $x = round($x0);

        foreach ($zones as $zone) {
            if ($x >= $zone['x_min'] && $x < $zone['x_max']) {
                return $zone['field'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array{colour_map: array<string, string>, format_unit: string}  $rules
     * @return array<string, string>
     */
    private function transform(array $fields, array $rules): array
    {
        if (isset($fields['colour'])) {
            $code = mb_strtolower(trim($fields['colour']));
            $fields['colour'] = $rules['colour_map'][$code] ?? $fields['colour'];
        }

        // "75/6" is the trade idiom for size/case — split it automatically.
        if (isset($fields['format_ml']) && str_contains($fields['format_ml'], '/')) {
            [$size, $case] = array_pad(explode('/', $fields['format_ml'], 2), 2, '');
            $fields['format_ml'] = trim($size);
            if (trim($fields['case_size'] ?? '') === '' && trim($case) !== '') {
                $fields['case_size'] = trim($case);
            }
        }

        if ($rules['format_unit'] !== '' && isset($fields['format_ml']) && preg_match('/^\d{1,4}(\.\d+)?$/', trim($fields['format_ml']))) {
            $fields['format_ml'] = trim($fields['format_ml']).$rules['format_unit'];
        }

        return $fields;
    }

    private function validRegex(string $pattern): string
    {
        if ($pattern === '' || strlen($pattern) > 500) {
            return '';
        }

        return @preg_match(self::wrap($pattern), '') === false ? '' : $pattern;
    }

    /**
     * Delimit an LLM-written pattern body safely (rules routinely contain `/`,
     * e.g. the 75/6 size-case idiom, so `~` is the delimiter).
     */
    private static function wrap(string $pattern): string
    {
        return '~'.str_replace('~', '\\~', $pattern).'~u';
    }
}
