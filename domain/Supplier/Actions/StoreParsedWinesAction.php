<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replaces a document's PROPOSED wines with a freshly parsed set (re-analysis
 * supersedes the previous run). Approved/rejected rows are preserved — they are
 * the reviewer's audit trail and feed recipe refinement. Transactional, with
 * chunked inserts so a 5,000-row monster list stays fast; uuid/timestamps are
 * filled manually since we bypass model events.
 */
class StoreParsedWinesAction extends AbstractAction
{
    /**
     * @param  array<int, array{payload: array<string, mixed>, confidence?: float|null, source_ref?: string|null, flag?: string|null}>  $rows
     * @return int number of rows stored
     */
    public function execute(int $documentId, int $supplierId, array $rows): int
    {
        return DB::transaction(function () use ($documentId, $supplierId, $rows): int {
            ParsedWine::where('supplier_document_id', $documentId)
                ->where('status', ParsedWineStatus::Proposed->value)
                ->delete();

            $now = Carbon::now();
            $stored = 0;

            foreach (array_chunk($rows, 500) as $chunk) {
                $records = array_map(fn (array $row) => [
                    'uuid' => (string) Str::uuid(),
                    'supplier_document_id' => $documentId,
                    'supplier_id' => $supplierId,
                    'payload' => json_encode($row['payload']),
                    'status' => ParsedWineStatus::Proposed->value,
                    'confidence' => $row['confidence'] ?? null,
                    'source_ref' => $row['source_ref'] ?? null,
                    'flag' => $row['flag'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);

                ParsedWine::insert($records);
                $stored += count($records);
            }

            return $stored;
        });
    }
}
