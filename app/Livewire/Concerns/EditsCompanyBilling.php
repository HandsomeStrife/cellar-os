<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Data\CompanyBillingData;
use Domain\Shared\Support\Currency;
use Domain\Shared\Support\MoneyInput;
use Illuminate\Validation\Rule;

/**
 * The commercial-terms half of a company form, shared by the create and edit
 * screens so the two can't drift on what a valid price is.
 *
 * The price field is validated by running it through {@see MoneyInput} rather
 * than by a regex: the parser understands "£49.50" and "1,299", and a stricter
 * pattern in front of it would reject exactly the inputs it exists to handle.
 */
trait EditsCompanyBilling
{
    public string $arrangement = 'standard';

    /** Entered in whole currency units ("49.50"), stored in minor units. */
    public string $customPrice = '';

    public string $customCurrency = 'GBP';

    public string $customInterval = 'month';

    public string $billingNotes = '';

    protected function currentArrangement(): BillingArrangement
    {
        $arrangement = BillingArrangement::tryFrom($this->arrangement);
        abort_if($arrangement === null, 422);

        return $arrangement;
    }

    /**
     * @return array<string, mixed>
     */
    protected function billingRules(BillingArrangement $arrangement): array
    {
        return [
            'plan' => ['required', Rule::in(array_column(Plan::cases(), 'value'))],
            'arrangement' => ['required', Rule::in(array_column(BillingArrangement::cases(), 'value'))],
            'customPrice' => $arrangement->needsPrice()
                ? ['required', $this->priceRule()]
                : ['nullable'],
            'customCurrency' => $arrangement->needsPrice()
                ? ['required', Rule::in(array_keys(Currency::SYMBOLS))]
                : ['nullable'],
            'customInterval' => $arrangement->needsPrice()
                ? ['required', Rule::in(array_column(BillingInterval::cases(), 'value'))]
                : ['nullable'],
            'billingNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Accept anything the money parser can read, and reject anything it can't
     * — including a price of nothing, which is a comp wearing a disguise.
     */
    protected function priceRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $minorUnits = MoneyInput::toMinorUnits(is_string($value) ? $value : null);

            if ($minorUnits === null) {
                $fail('Write the amount in pounds and pence, like 49.50.');

                return;
            }

            if ($minorUnits <= 0) {
                $fail('A price of nothing is a free account. Choose the Free arrangement instead.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function billingMessages(): array
    {
        return [
            'customPrice.required' => 'Give the agreed amount, or choose a different arrangement.',
        ];
    }

    /**
     * The terms as entered. Custom-price fields are passed through as typed;
     * {@see CompanyBillingData::normalised()} is what decides whether they
     * survive, so that rule lives in the domain rather than in two forms.
     */
    protected function billingTerms(Plan $plan, BillingArrangement $arrangement, ?CarbonImmutable $trialEndsAt): CompanyBillingData
    {
        return new CompanyBillingData(
            plan: $plan,
            billing_arrangement: $arrangement,
            custom_price_amount: MoneyInput::toMinorUnits($this->customPrice),
            custom_price_currency: strtoupper($this->customCurrency),
            custom_price_interval: BillingInterval::tryFrom($this->customInterval),
            billing_notes: $this->billingNotes === '' ? null : $this->billingNotes,
            trial_ends_at: $trialEndsAt,
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function billingOptions(): array
    {
        return [
            'plans' => collect(Plan::cases())->mapWithKeys(fn (Plan $p) => [$p->value => $p->getLabel()])->all(),
            'arrangements' => BillingArrangement::options(),
            'intervals' => BillingInterval::options(),
            'currencies' => collect(array_keys(Currency::SYMBOLS))->mapWithKeys(fn (string $c) => [$c => $c])->all(),
        ];
    }
}
