<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Domain\Admin\Data\AdminData;
use Domain\Admin\Repositories\AdminRepository;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin impersonation of buyers (web guard) and supplier-portal users
 * (supplier guard). The admin guard STAYS authenticated throughout — guards
 * are independent within the session — so "stop" simply logs the impersonated
 * guard out and returns to the admin console. A banner (layouts app/supplier)
 * is shown whenever the impersonation flag is set. Every start/stop is logged.
 */
class ImpersonationController
{
    public function user(int $id): RedirectResponse
    {
        $admin = $this->requireAdmin();
        $user = User::findOrFail($id);

        session([
            'impersonator_admin_id' => $admin->id,
            'impersonating_guard' => 'web',
            'impersonate_return' => url()->previous(),
        ]);
        Auth::guard('web')->login($user);

        Log::info('Admin impersonation started', ['admin_id' => $admin->id, 'guard' => 'web', 'user_id' => $user->id, 'email' => $user->email]);

        return redirect()->route('dashboard');
    }

    public function supplierUser(int $id): RedirectResponse
    {
        $admin = $this->requireAdmin();
        $supplierUser = SupplierUser::findOrFail($id);

        session([
            'impersonator_admin_id' => $admin->id,
            'impersonating_guard' => 'supplier',
            'impersonate_return' => url()->previous(),
        ]);
        // Works for invite-pending users too (no password yet) — exactly the
        // accounts an admin most needs to preview.
        Auth::guard('supplier')->login($supplierUser);

        Log::info('Admin impersonation started', ['admin_id' => $admin->id, 'guard' => 'supplier', 'supplier_user_id' => $supplierUser->id, 'email' => $supplierUser->email]);

        return redirect()->route('supplier.dashboard');
    }

    public function stop(): RedirectResponse
    {
        $admin = $this->requireAdmin();
        $guard = (string) session('impersonating_guard', '');
        $return = (string) session('impersonate_return', '');

        if (in_array($guard, ['web', 'supplier'], true)) {
            Log::info('Admin impersonation stopped', ['admin_id' => $admin->id, 'guard' => $guard]);
            Auth::guard($guard)->logout();
        }

        session()->forget(['impersonator_admin_id', 'impersonating_guard', 'impersonate_return']);

        return redirect()->to($return !== '' ? $return : route('admin.dashboard'));
    }

    private function requireAdmin(): AdminData
    {
        $admin = (new AdminRepository)->getLoggedInAdmin();
        abort_if($admin === null, 403);

        return $admin;
    }
}
