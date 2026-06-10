<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Admin\Models\Admin;
use Illuminate\Console\Command;

/**
 * Revokes an admin's API tokens (all, or by name) — the kill switch for a
 * leaked ingestion token.
 */
class RevokeApiTokens extends Command
{
    protected $signature = 'api:revoke-tokens {email : admin email} {--name= : only tokens with this name}';

    protected $description = 'Revoke API tokens issued to an admin.';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin with that email.');

            return self::FAILURE;
        }

        $query = $admin->tokens();

        if (($name = (string) $this->option('name')) !== '') {
            $query->where('name', $name);
        }

        $count = $query->delete();
        $this->info("Revoked {$count} token(s).");

        return self::SUCCESS;
    }
}
