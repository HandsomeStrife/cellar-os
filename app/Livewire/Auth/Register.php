<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

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
    #[Validate('required|string|max:255')]
    public string $full_name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterUserAction $action)
    {
        $this->validate();

        $user = $action->execute(new RegisterUserData(
            full_name: $this->full_name,
            email: $this->email,
            password: $this->password,
        ));

        Auth::loginUsingId($user->id);
        session()->regenerate();

        return $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
