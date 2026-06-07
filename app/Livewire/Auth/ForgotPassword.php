<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    public function sendResetLink()
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
            $this->reset('email');

            return;
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
