<?php

declare(strict_types=1);

namespace Domain\Supplier\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed progress for a document's queued bulk approve-all, so the
 * review screens can poll a background job without a schema change.
 * States: queued -> running -> done | failed. Entries expire after an hour.
 */
class BulkApprovalProgress
{
    private const TTL_SECONDS = 3600;

    public static function queued(int $document_id): void
    {
        Cache::put(self::key($document_id), ['state' => 'queued', 'approved' => 0, 'total' => null], self::TTL_SECONDS);
    }

    public static function start(int $document_id, int $total): void
    {
        Cache::put(self::key($document_id), ['state' => 'running', 'approved' => 0, 'total' => $total], self::TTL_SECONDS);
    }

    public static function update(int $document_id, int $approved): void
    {
        $current = self::get($document_id);
        Cache::put(self::key($document_id), [
            'state' => 'running',
            'approved' => $approved,
            'total' => $current['total'] ?? null,
        ], self::TTL_SECONDS);
    }

    public static function finish(int $document_id, int $approved): void
    {
        $current = self::get($document_id);
        Cache::put(self::key($document_id), [
            'state' => 'done',
            'approved' => $approved,
            'total' => $current['total'] ?? null,
        ], self::TTL_SECONDS);
    }

    public static function fail(int $document_id, string $message): void
    {
        $current = self::get($document_id);
        Cache::put(self::key($document_id), [
            'state' => 'failed',
            'approved' => $current['approved'] ?? 0,
            'total' => $current['total'] ?? null,
            'message' => $message,
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{state: string, approved: int|null, total: int|null, message?: string}|null
     */
    public static function get(int $document_id): ?array
    {
        return Cache::get(self::key($document_id));
    }

    public static function clear(int $document_id): void
    {
        Cache::forget(self::key($document_id));
    }

    /**
     * Whether an approval is queued or mid-run (used to refuse a duplicate).
     */
    public static function isActive(int $document_id): bool
    {
        return in_array(self::get($document_id)['state'] ?? null, ['queued', 'running'], true);
    }

    private static function key(int $document_id): string
    {
        return "bulk-approve:document:{$document_id}";
    }
}
