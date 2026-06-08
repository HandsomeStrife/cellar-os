<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Domain\Shared\Support\Currency;
use Domain\User\Actions\RegisterUserAction;
use Domain\User\Data\RegisterUserData;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Create account')]
class Register extends Component
{
    public const PROFESSIONS = ['Owner', 'Buyer', 'Manager', 'Sommelier', 'Consultant'];

    #[Validate('required|string|max:255')]
    public string $full_name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    #[Validate('nullable|string|max:255')]
    public string $company_name = '';

    #[Validate('nullable|string|in:Owner,Buyer,Manager,Sommelier,Consultant')]
    public string $profession = '';

    #[Validate('required|string|in:GBP,EUR,USD')]
    public string $base_currency = 'GBP';

    public function register(RegisterUserAction $action)
    {
        $this->validate();

        $user = $action->execute(new RegisterUserData(
            full_name: $this->full_name,
            email: $this->email,
            password: $this->password,
            profession: $this->profession ?: null,
            company_name: $this->company_name ?: null,
            base_currency: $this->base_currency,
        ));

        Auth::loginUsingId($user->id);
        session()->regenerate();

        return $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'professions' => self::PROFESSIONS,
            'currencies' => array_keys(Currency::SYMBOLS),
        ]);
    }
}
