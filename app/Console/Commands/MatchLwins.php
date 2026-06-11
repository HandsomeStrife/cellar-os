<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Services\LwinMatchService;
use Illuminate\Console\Command;

/**
 * Links the catalogue and wine-facts store to LWIN reference codes. Run after
 * wine:lwin-refresh, and again after big imports. Deterministic passes are
 * free; --llm adds a capped, cheap model pass over the residue.
 */
class MatchLwins extends Command
{
    protected $signature = 'wine:lwin-match {--llm : resolve residue with the model} {--llm-limit=500} {--model=claude-haiku-4-5}';

    protected $description = 'Match products and wine facts to LWIN codes.';

    public function handle(LwinMatchService $matcher): int
    {
        if (Lwin::count() === 0) {
            $this->error('The lwins table is empty — run wine:lwin-refresh first.');

            return self::FAILURE;
        }

        $stats = $matcher->match(
            withLlm: (bool) $this->option('llm'),
            llmLimit: (int) $this->option('llm-limit'),
            model: (string) $this->option('model') ?: null,
        );

        foreach ($stats as $entity => $counts) {
            $this->info(sprintf(
                '%s: %d via identity, %d via name, %d via llm, %d via product, %d unmatched',
                $entity,
                $counts['identity'],
                $counts['name'],
                $counts['llm'],
                $counts['product'] ?? 0,
                $counts['unmatched'],
            ));
        }

        return self::SUCCESS;
    }
}
