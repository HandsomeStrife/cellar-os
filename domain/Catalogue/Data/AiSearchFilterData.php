<?php

declare(strict_types=1);

namespace Domain\Catalogue\Data;

use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Shared\Data\AbstractData;

/**
 * A plain-English catalogue query, read into the filters the catalogue already
 * has. Deliberately nothing more: the model never writes a query, it only
 * chooses values for fields the buyer could have set by hand — which is what
 * makes the result explainable and keeps tenant scoping out of its reach.
 */
class AiSearchFilterData extends AbstractData
{
    public function __construct(
        public string $summary = '',
        public string $search = '',
        public ?WineType $type = null,
        public ?WineSubType $sub_type = null,
        public string $country = '',
        public string $region = '',
        public string $producer = '',
        public string $grape = '',
        public ?float $price_min = null,
        public ?float $price_max = null,
        public ?int $vintage_min = null,
        public ?int $vintage_max = null,
    ) {}

    /**
     * Build from the model's structured output, which is all-strings by API
     * constraint. Anything unrecognised is dropped rather than guessed at — a
     * filter we can't honour must not silently narrow the results.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromModelOutput(array $raw): self
    {
        $text = fn (string $key) => trim((string) ($raw[$key] ?? ''));
        $number = function (string $key) use ($text): ?float {
            $value = $text($key);

            return $value === '' || ! is_numeric($value) ? null : (float) $value;
        };

        $type = WineType::tryFrom($text('type'));
        $subType = WineSubType::tryFrom($text('sub_type'));

        return new self(
            summary: $text('summary'),
            search: $text('search'),
            type: $type,
            // A sub-type that doesn't belong to the chosen type is discarded
            // rather than allowed to contradict it.
            sub_type: $subType?->parent() === $type ? $subType : null,
            country: $text('country'),
            region: $text('region'),
            producer: $text('producer'),
            grape: $text('grape'),
            price_min: $number('price_min'),
            price_max: $number('price_max'),
            vintage_min: ($v = $number('vintage_min')) !== null ? (int) $v : null,
            vintage_max: ($v = $number('vintage_max')) !== null ? (int) $v : null,
        );
    }

    /**
     * Whether the model actually understood anything worth filtering on.
     */
    public function isEmpty(): bool
    {
        return $this->search === ''
            && $this->type === null
            && $this->sub_type === null
            && $this->country === ''
            && $this->region === ''
            && $this->producer === ''
            && $this->grape === ''
            && $this->price_min === null
            && $this->price_max === null
            && $this->vintage_min === null
            && $this->vintage_max === null;
    }

    /**
     * The criteria in plain words, for the removable chips above the results.
     *
     * @return array<string, string> filter property => human label
     */
    public function chips(): array
    {
        $chips = [];

        if ($this->search !== '') {
            $chips['search'] = '"'.$this->search.'"';
        }
        if ($this->type !== null) {
            $chips['colour'] = $this->type->getLabel();
        }
        if ($this->sub_type !== null) {
            $chips['sub_type'] = $this->sub_type->getShortLabel();
        }
        if ($this->country !== '') {
            $chips['country'] = $this->country;
        }
        if ($this->region !== '') {
            $chips['region'] = $this->region;
        }
        if ($this->producer !== '') {
            $chips['producer'] = $this->producer;
        }
        if ($this->grape !== '') {
            $chips['grape'] = $this->grape;
        }
        if ($this->price_min !== null) {
            $chips['priceMin'] = 'from '.number_format($this->price_min, 2);
        }
        if ($this->price_max !== null) {
            $chips['priceMax'] = 'up to '.number_format($this->price_max, 2);
        }
        if ($this->vintage_min !== null) {
            $chips['vintageMin'] = $this->vintage_min.' or later';
        }
        if ($this->vintage_max !== null) {
            $chips['vintageMax'] = $this->vintage_max.' or earlier';
        }

        return $chips;
    }
}
