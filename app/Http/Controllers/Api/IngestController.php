<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Domain\Catalogue\Actions\ImportCatalogueWinesAction;
use Domain\Catalogue\Actions\ImportWineFactsAction;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Catalogue\Repositories\WineFactRepository;
use Domain\Supplier\Actions\ImportListedSuppliersAction;
use Domain\Supplier\Actions\ImportParseProfilesAction;
use Domain\Supplier\Repositories\SupplierRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machine ingestion of canonical trade data (token-auth, ability:ingestion) —
 * the HTTP transport for the golden-snapshot payload format. Lets a dev box
 * parse documents locally (where the LLM key and review UI live) and push the
 * approved results to a remote, which therefore never needs an LLM key, and
 * lets future fetchers (e.g. a Farr CSV cron) feed the platform directly.
 */
class IngestController
{
    private const MAX_BATCH = 500;

    public function suppliers(Request $request): JsonResponse
    {
        $rows = $this->rows($request);
        $result = (new ImportListedSuppliersAction)->execute($rows);

        return response()->json(['imported' => $result['count']]);
    }

    public function wines(Request $request): JsonResponse
    {
        $rows = $this->rows($request);
        $result = (new ImportCatalogueWinesAction)->execute($rows, (new SupplierRepository)->publicNameMap());

        return response()->json($result);
    }

    public function facts(Request $request): JsonResponse
    {
        $rows = $this->rows($request);

        return response()->json(['imported' => (new ImportWineFactsAction)->execute($rows)]);
    }

    public function parseProfiles(Request $request): JsonResponse
    {
        $rows = $this->rows($request);

        return response()->json(['imported' => (new ImportParseProfilesAction)->execute($rows, (new SupplierRepository)->publicNameMap())]);
    }

    /**
     * Counts for post-push verification.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'public_suppliers' => count((new SupplierRepository)->publicNameMap()),
            'products' => (new ProductRepository)->count(),
            'wine_facts' => (new WineFactRepository)->count(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(Request $request): array
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'rows.*' => ['array'],
        ]);

        return $validated['rows'];
    }
}
