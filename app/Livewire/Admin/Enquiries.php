<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Enquiry\Actions\DeleteEnquiryAction;
use Domain\Enquiry\Actions\MarkEnquiryStatusAction;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Repositories\EnquiryRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Enquiries')]
class Enquiries extends Component
{
    use WithPagination;

    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function mark(string $uuid, string $status): void
    {
        $this->ensureAdmin();

        $statusEnum = EnquiryStatus::tryFrom($status);
        abort_if($statusEnum === null, 422);

        (new MarkEnquiryStatusAction)->execute($uuid, $statusEnum);
        $this->dispatch('toast', message: 'Enquiry updated.');
    }

    public function deleteEnquiry(string $uuid): void
    {
        $this->ensureAdmin();

        (new DeleteEnquiryAction)->execute($uuid);
        $this->dispatch('toast', message: 'Enquiry deleted.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function render()
    {
        $filter = EnquiryStatus::tryFrom($this->status);

        return view('livewire.admin.enquiries', [
            'enquiries' => (new EnquiryRepository)->paginate($filter),
            'statuses' => EnquiryStatus::cases(),
        ]);
    }
}
