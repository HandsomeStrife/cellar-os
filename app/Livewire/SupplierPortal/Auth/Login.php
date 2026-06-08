<?php

declare(strict_types=1);

namespace App\Livewire\SupplierPortal\Auth;

use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Supplier sign in')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('supplier')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // Block access when the supplier company itself has been deactivated.
        $supplierId = (new SupplierUserRepository)->getLoggedInSupplierUser()?->supplier_id;
        $supplier = $supplierId ? (new SupplierRepository)->find($supplierId) : null;

        if ($supplier === null || $supplier->status !== SupplierStatus::Active) {
            Auth::guard('supplier')->logout();

            throw ValidationException::withMessages(['email' => 'This supplier account is not active. Please contact CellarOS.']);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        return $this->redirectIntended(route('supplier.dashboard'), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return 'supplier|'.Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.supplier-portal.auth.login');
    }
}
