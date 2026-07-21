<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

/**
 * Deterministic parser for trade lists that carry a "CLASSIFIED PRICE CHECK" —
 * a style-grouped, price-sorted index of the whole portfolio (Les Caves de
 * Pyrene's format). The index has a CLEAN text layer (one wine per two lines,
 * exact prices), unlike the producer-grid half of the same document whose text
 * layer is scrambled. We therefore parse the index for coverage + prices, then
 * enrich each wine with GRAPE (and a fuller producer) recovered from the grid
 * via OCR — the grape column exists only in the grid.
 *
 * Everything here is $0 and deterministic: no LLM. The section is located by
 * CONTENT (the per-row "- CLASSIFIED" / "SKIN CONTACT" tag), never by fixed
 * page numbers, so it survives edition-to-edition pagination changes.
 *
 * The parser emits raw rows keyed by ClaudeClient::FIELDS; DocumentAnalysis-
 * Service feeds them through Import\NormaliseService exactly like the other
 * strategies, so colour/grape/geo normalisation is shared.
 */
class ClassifiedPriceListParser
{
    /** A classified row's trailing style tag → catalogue colour. */
    private const COLOUR_BY_TAG = [
        'RED WINES' => 'Red',
        'WHITE WINES' => 'White',
        'SPARKLING WINES' => 'Sparkling',
        'ROSÉ WINES' => 'Rosé',
        'ROSE WINES' => 'Rosé',
        'ORANGE/SKIN CONTACT' => 'Orange',
        'ORANGE/SKIN CONTACT WINES' => 'Orange',
        'SWEET WINES' => 'White',
        'SHERRY' => 'Fortified',
        'SHERRY-STYLE' => 'Fortified',
        'VERMOUTH' => 'Fortified',
        'OTHER FORTIFIED WINES' => 'Fortified',
    ];

    /**
     * Bottle size (in cl, for NormaliseService) implied by a format-only
     * section whose price line omits the size. Without this the row defaults to
     * 750ml and collides with the standard bottle, overwriting its price — the
     * magnum/half-bottle price would land on the 750ml wine.
     */
    private const FORMAT_CL_BY_TAG = [
        'MAGNUMS' => '150cl',       // 1500ml
        'HALF BOTTLES' => '37.5cl', // 375ml
    ];

    /** The union of every recognised style tag, for the row/section regex.
     * Order matters: longer/more-specific tags precede their prefixes
     * (SHERRY-STYLE before SHERRY, the *-WINES variants before bare WINES). */
    private const TAG_ALTERNATION = 'RED WINES|WHITE WINES|SPARKLING WINES|ROSÉ WINES|ROSE WINES|ORANGE/SKIN CONTACT WINES|ORANGE/SKIN CONTACT|SWEET WINES|OTHER FORTIFIED WINES|SHERRY-STYLE|SHERRY|VERMOUTH|MAGNUMS|HALF BOTTLES|BAG-IN-BOX|KEG/KEYKEGS|POLYKEGS|CIDERS/PERRIES|SAKE|BITTER|BOX|STYLE|WINES';

    /**
     * Does this document contain a classified price-check section? Cheap
     * content probe used by the study step to pick this strategy.
     */
    public function looksClassified(string $layoutText): bool
    {
        // The per-row tag is unique to the index; the grid half never uses it.
        return preg_match('/\bCLASSIFIED PRICE CHECK\b/i', $layoutText) === 1
            || substr_count($layoutText, '- CLASSIFIED') >= 20;
    }

    /**
     * Parse the classified index out of the document's layout text. The anchor
     * is the PRICE line (£x.xx, in several shapes); the wine sits on the
     * preceding non-blank line as "[code] VINTAGE  Name, Producer - Region,
     * Country   <STYLE TAG>". Colour comes from the tag (or the last standalone
     * section header). Rows without a leading vintage above a price (cordials,
     * olive oil, cooking wine) are not wines and are skipped.
     *
     * @return array<int, array{fields: array<string, string>, page: int|null}>
     */
    public function parseIndex(string $layoutText): array
    {
        $lines = preg_split('/\r?\n/', $layoutText) ?: [];

        // Track page breaks (form-feed) so each wine keeps a page source_ref.
        $priceRe = '/£\s?([\d,]+\.\d{2})(?:\s*LIST)?(?:\s+([\d.]+)\s*cl)?(?:\s+([\d.]+)\s*%)?/u';
        // `#` delimiter: the tag alternation contains slashes (ORANGE/SKIN,
        // KEG/KEYKEGS) that would otherwise close a `/`-delimited pattern.
        $tag = self::TAG_ALTERNATION;
        // Some tags read "… - CLASSIFIED LIST" rather than "… - CLASSIFIED".
        $nameRe = '#^\s*(?:[A-Z]{1,3}\s+)?((?:19|20)\d{2}|NV)\s+(.+?)(?:\s{3,}('.$tag.')(?:\s*-\s*CLASSIFIED(?:\s+LIST)?)?\s*)?$#u';
        $sectionRe = '#^\s*('.$tag.')\s*-?\s*CLASSIFIED?(?:\s+LIST)?\s*$#u';

        $page = 1;
        $pageOfLine = [];
        foreach ($lines as $i => $line) {
            $page += substr_count($line, "\f");
            $pageOfLine[$i] = $page;
        }

        // Only the index is parsed here — never the scrambled producer grid
        // that precedes it (whose stray price lines would yield garbled rows).
        // The index begins at the "CLASSIFIED PRICE CHECK" heading, else the
        // first row carrying the "- CLASSIFIED" tag. Content-anchored, so it is
        // immune to edition page-drift.
        $lastSection = null;
        $out = [];
        $count = count($lines);
        $start = $this->indexStartLine($lines);

        for ($i = $start; $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match($sectionRe, $line, $sm)) {
                $lastSection = $sm[1];
            }

            if (! str_contains($line, '£') || ! preg_match($priceRe, $line, $pm)) {
                continue;
            }

            $j = $this->previousNonBlank($lines, $i);
            if ($j === null || ! preg_match($nameRe, $lines[$j], $nm)) {
                continue;
            }

            $tagValue = $nm[3] ?? $lastSection;
            $fields = $this->rowFields($nm[1], trim($nm[2]), $tagValue, $pm);

            if ($fields !== null) {
                $out[] = ['fields' => $fields, 'page' => $pageOfLine[$j] ?? null];
            }
        }

        return $out;
    }

    /**
     * Build the raw field row for one classified wine. Splits the packed
     * "Name, Producer - Region, Country" descriptor into discrete columns and
     * derives colour from the style tag.
     *
     * @param  array<int, string>  $priceMatch
     * @return array<string, string>|null
     */
    private function rowFields(string $vintage, string $descriptor, ?string $tag, array $priceMatch): ?array
    {
        // The index opens with a short non-alcoholic block (cordials, grape
        // juice, fermented teas). Most lack a vintage and never reach here;
        // the few that carry an "NV" are excluded by their explicit label.
        if (preg_match('/non[\s-]?alcoholic/iu', $descriptor)) {
            return null;
        }

        $tagKey = $tag !== null ? mb_strtoupper(trim($tag)) : null;

        // Bottle size: an explicit "cl" on the price line wins; else a
        // "N Litre" hint in the name (bag-in-box / keg); else the size implied
        // by a format-only section (magnum / half). Getting this right is what
        // stops a magnum/half row from collapsing onto the 750ml bottle and
        // overwriting its price.
        $formatMl = null;
        if (isset($priceMatch[2]) && $priceMatch[2] !== '') {
            $formatMl = $priceMatch[2].'cl';
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*Litre/iu', $descriptor, $lm)) {
            $formatMl = (((float) $lm[1]) * 100).'cl';
        } elseif ($tagKey !== null && isset(self::FORMAT_CL_BY_TAG[$tagKey])) {
            $formatMl = self::FORMAT_CL_BY_TAG[$tagKey];
        }

        // Strip a trailing "- N Litre BIB/KEG …" size hint so it never lands in
        // the country column.
        $descriptor = preg_replace('/\s*-\s*\d+(?:\.\d+)?\s*Litre\b.*$/iu', '', $descriptor) ?? $descriptor;

        [$name, $producer, $region, $country] = $this->splitDescriptor($descriptor);

        if ($name === '') {
            return null;
        }

        $fields = [
            'wine_name' => $name,
            'vintage' => $vintage,
            'unit_price' => str_replace(',', '', $priceMatch[1]),
        ];

        if ($producer !== null) {
            $fields['producer'] = $producer;
        }
        if ($region !== null) {
            $fields['region'] = $region;
        }
        if ($country !== null) {
            $fields['country'] = $country;
        }
        if ($formatMl !== null) {
            $fields['format_ml'] = $formatMl;
        }

        if ($tagKey !== null && isset(self::COLOUR_BY_TAG[$tagKey])) {
            $fields['colour'] = self::COLOUR_BY_TAG[$tagKey];
        }

        return $fields;
    }

    /**
     * "Name, Producer - Region, Country" → [name, producer, region, country].
     * Handles the degraded shapes too: "Name, Producer - Country" (no region)
     * and "Name - Region, Country" (no producer).
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?string}
     */
    public function splitDescriptor(string $text): array
    {
        $name = trim($text);
        $producer = $region = $country = null;

        if (str_contains($text, ' - ')) {
            [$left, $right] = explode(' - ', $text, 2);

            if (str_contains($right, ',')) {
                $parts = explode(',', $right);
                $country = trim(array_pop($parts));
                $region = trim(implode(',', $parts)) ?: null;
            } else {
                $country = trim($right) ?: null;
            }

            if (str_contains($left, ',')) {
                [$name, $producer] = explode(',', $left, 2);
                $name = trim($name);
                $producer = trim($producer) ?: null;
            } else {
                $name = trim($left);
            }
        } elseif (str_contains($text, ',')) {
            [$name, $producer] = explode(',', $text, 2);
            $name = trim($name);
            $producer = trim($producer) ?: null;
        }

        return [$name, $producer, $region, $country];
    }

    /**
     * Extract (vintage, price, name, grape, producer) records from OCR text of
     * the producer-grid pages. OCR renders each grid wine on ONE line, e.g.
     * "2021  ZOLD ~ Sylvaner   Orange  £21.50  75.00cl  11.00%" — the grape
     * follows a tilde. Only rows that actually carry a grape are worth keeping
     * (that is the whole reason we OCR the grid).
     *
     * @param  array<int, string>  $pageTexts
     * @return array<int, array{vintage: string, price: float, name: string, grape: string, producer: ?string}>
     */
    public function extractGridGrapes(array $pageTexts): array
    {
        $records = [];

        foreach ($pageTexts as $text) {
            foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
                if (! preg_match('/£\s?([\d,]+\.\d{2})/u', $line, $pm)) {
                    continue;
                }
                if (! preg_match('/^\s*(?:[A-Z]{1,4}\s+)?((?:19|20)\d{2}|NV)\b(.*)$/u', $line, $vm)) {
                    continue;
                }

                // Everything between the vintage and the price is name (+ grape).
                $body = $vm[2];
                $body = preg_replace('/£.*$/u', '', $body) ?? $body;
                // OCR sometimes reads the faint ghost layer as "~~"/"~=" noise.
                $body = preg_replace('/~[~=]+/u', ' ', $body) ?? $body;

                if (! preg_match('/~\s*([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ,\'\s\-]+?)\s*$/u', $body, $gm)) {
                    continue; // no grape on this row
                }

                $grape = trim(preg_replace('/\s+/u', ' ', $gm[1]));
                $grape = $this->stripTrailingStyle($grape);
                if ($grape === '' || mb_strlen($grape) < 3) {
                    continue;
                }

                $name = trim(preg_replace('/~.*$/u', '', $body) ?? $body);
                $name = $this->stripTrailingStyle($name);
                $name = trim(preg_replace('/\s+/u', ' ', $name));
                if ($name === '') {
                    continue;
                }

                $records[] = [
                    'vintage' => $vm[1],
                    'price' => (float) str_replace(',', '', $pm[1]),
                    'name' => $name,
                    'grape' => $grape,
                    'producer' => null,
                ];
            }
        }

        return $records;
    }

    /** Drop a trailing style/colour word OCR left glued to the field. */
    private function stripTrailingStyle(string $s): string
    {
        return trim(preg_replace('/\s+(White|Red|Ros[eé]|Orange|Sparkling(?:\/\w+)?|Sweet|Fortified|Gin|Whisky)\s*$/u', '', $s) ?? $s);
    }

    /**
     * Merge grape (and producer, when missing) from grid records onto the
     * classified backbone. Because prices AGREE between the two sections, the
     * match key is (vintage + price ± 1p) confirmed by a shared name token —
     * tight enough to avoid cross-wine bleed.
     *
     * @param  array<int, array{fields: array<string, string>, page: int|null}>  $backbone
     * @param  array<int, array{vintage: string, price: float, name: string, grape: string, producer: ?string}>  $grid
     * @return array{rows: array<int, array{fields: array<string, string>, page: int|null}>, enriched: int}
     */
    public function mergeGrapes(array $backbone, array $grid): array
    {
        // Index grid records by vintage for a cheap first-pass narrowing.
        $byVintage = [];
        foreach ($grid as $g) {
            $byVintage[$g['vintage']][] = $g;
        }

        $enriched = 0;

        foreach ($backbone as &$wine) {
            if (($wine['fields']['grape'] ?? '') !== '') {
                continue;
            }

            $vintage = $wine['fields']['vintage'] ?? '';
            $price = (float) ($wine['fields']['unit_price'] ?? 0);
            $nameTokens = $this->tokens($wine['fields']['wine_name'] ?? '');
            if ($nameTokens === [] || ! isset($byVintage[$vintage])) {
                continue;
            }

            $best = null;
            $bestOverlap = 0;
            foreach ($byVintage[$vintage] as $g) {
                if (abs($g['price'] - $price) > 0.02) {
                    continue;
                }
                $overlap = count(array_intersect($nameTokens, $this->tokens($g['name'])));
                if ($overlap > $bestOverlap) {
                    $bestOverlap = $overlap;
                    $best = $g;
                }
            }

            if ($best !== null && $bestOverlap > 0) {
                $wine['fields']['grape'] = $best['grape'];
                $enriched++;
            }
        }
        unset($wine);

        return ['rows' => $backbone, 'enriched' => $enriched];
    }

    /**
     * Lower-cased alphabetic tokens of 4+ chars — the matching vocabulary
     * (drops "de", "du", vintages, punctuation).
     *
     * @return array<int, string>
     */
    private function tokens(string $s): array
    {
        preg_match_all('/[a-zà-ÿ]{4,}/u', mb_strtolower($s), $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * First line of the classified index: the "CLASSIFIED PRICE CHECK" heading
     * if present, else the first line bearing the "- CLASSIFIED" row tag.
     *
     * @param  array<int, string>  $lines
     */
    private function indexStartLine(array $lines): int
    {
        $firstTag = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/CLASSIFIED PRICE CHECK/i', $line)) {
                return $i;
            }
            if ($firstTag === null && str_contains($line, '- CLASSIFIED')) {
                $firstTag = $i;
            }
        }

        return $firstTag ?? 0;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function previousNonBlank(array $lines, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (trim($lines[$j]) !== '') {
                return $j;
            }
        }

        return null;
    }
}
