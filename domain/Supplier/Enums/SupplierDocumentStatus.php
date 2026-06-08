<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

enum SupplierDocumentStatus: string
{
    case AwaitingAnalysis = 'AwaitingAnalysis';
    case Analysing = 'Analysing';
    case Analysed = 'Analysed';
    case Failed = 'Failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::AwaitingAnalysis => 'Awaiting analysis',
            self::Analysing => 'Analysing',
            self::Analysed => 'Analysed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Badge colour token (see x-badge).
     */
    public function getColour(): string
    {
        return match ($this) {
            self::AwaitingAnalysis => 'amber',
            self::Analysing => 'blue',
            self::Analysed => 'green',
            self::Failed => 'red',
        };
    }

    /**
     * @return array<string, string> value => label, for select options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
