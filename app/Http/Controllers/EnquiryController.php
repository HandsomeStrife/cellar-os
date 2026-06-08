<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Enquiry\Actions\StoreEnquiryAction;
use Domain\Enquiry\Data\EnquiryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnquiryController
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        (new StoreEnquiryAction)->execute(new EnquiryData(
            id: null,
            uuid: null,
            name: $validated['name'],
            email: $validated['email'],
            company: $validated['company'] ?? null,
            message: $validated['message'],
        ));

        return redirect()
            ->to(route('home').'#contact')
            ->with('enquiry_success', true);
    }
}
