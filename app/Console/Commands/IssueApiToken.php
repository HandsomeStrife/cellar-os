<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Admin\Models\Admin;
use Illuminate\Console\Command;

/**
 * Issues a Sanctum machine token (bound to an admin) for the ingestion API.
 * The plaintext token is shown ONCE — store it in the pushing environment.
 */
class IssueApiToken extends Command
{
    protected $signature = 'api:issue-token {email : admin email} {--name=ingestion : token name} {--ability=ingestion : token ability} {--expires=90 : days until expiry (0 = never)}';

    protected $description = 'Issue an API token for the ingestion endpoints.';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin with that email.');

            return self::FAILURE;
        }

        $days = (int) $this->option('expires');
        $token = $admin->createToken(
            (string) $this->option('name'),
            [(string) $this->option('ability')],
            $days > 0 ? now()->addDays($days) : null,
        );

        $this->info('Token (shown once — store it now):');
        $this->line($token->plainTextToken);
        $this->line($days > 0 ? "Expires in {$days} day(s)." : 'Never expires.');

        return self::SUCCESS;
    }
}
