{{-- Shown while an admin is impersonating a buyer or supplier-portal user.
     The admin guard stays authenticated; stopping returns to the console. --}}
@if(session()->has('impersonator_admin_id') && auth('admin')->check())
    <div class="sticky top-0 z-50 flex items-center justify-center gap-3 bg-primary px-4 py-2 text-sm text-primary-foreground">
        <x-icon.eye class="size-4" aria-hidden="true" />
        <span>
            Viewing as
            <strong>
                @if(session('impersonating_guard') === 'supplier')
                    {{ auth('supplier')->user()?->email }}
                @else
                    {{ auth('web')->user()?->email }}
                @endif
            </strong>
            — you are impersonating this account as an administrator.
        </span>
        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
            @csrf
            <button type="submit" class="rounded-md border border-primary-foreground/40 px-3 py-1 font-medium transition hover:bg-primary-foreground/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-foreground">
                Return to admin
            </button>
        </form>
    </div>
@endif
