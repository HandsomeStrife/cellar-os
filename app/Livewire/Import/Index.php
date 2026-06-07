<?php

declare(strict_types=1);

namespace App\Livewire\Import;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Import\Actions\MarkRawUploadImportedAction;
use Domain\Import\Actions\StoreRawUploadAction;
use Domain\Import\Repositories\RawUploadRepository;
use Domain\Import\Services\NormaliseService;
use Domain\Import\Services\PriceListParser;
use Domain\Supplier\Actions\SaveColumnMappingAction;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\User\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Import')]
class Index extends Component
{
    use WithFileUploads;

    /** Mappable product fields => label. wine_name is required. */
    public const FIELDS = [
        'wine_name' => 'Wine name',
        'producer' => 'Producer',
        'country' => 'Country',
        'region' => 'Region',
        'sub_region' => 'Sub-region',
        'grape' => 'Grape(s)',
        'colour' => 'Colour',
        'vintage' => 'Vintage',
        'format_ml' => 'Format / size',
        'case_size' => 'Case size',
        'unit_price' => 'Unit price',
        'stock' => 'Stock',
    ];

    public int $step = 1;

    public ?int $supplierId = null;

    public $upload;

    public ?int $rawUploadId = null;

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<string, string> */
    public array $mapping = [];

    public ?int $importedCount = null;

    private function plan(): Plan
    {
        return (new UserRepository)->getLoggedInUser()?->plan ?? Plan::Free;
    }

    private function entitled(): bool
    {
        return $this->plan()->can(Feature::ImportSupplierLists);
    }

    public function uploadFile(): void
    {
        abort_unless($this->entitled(), 403);

        $this->validate([
            'supplierId' => 'required|integer|exists:suppliers,id',
            'upload' => 'required|file|max:10240|mimes:csv,txt,xls,xlsx',
        ]);

        try {
            $parsed = (new PriceListParser)->parse(
                $this->upload->getRealPath(),
                $this->upload->getClientOriginalExtension(),
            );
        } catch (\Throwable) {
            $this->addError('upload', 'We could not read that file. Please check it is a valid CSV or Excel file.');

            return;
        }

        if ($parsed['rows'] === []) {
            $this->addError('upload', 'No data rows were found in that file.');

            return;
        }

        $upload = (new StoreRawUploadAction)->execute(
            supplierId: $this->supplierId,
            uploadedBy: (new UserRepository)->getLoggedInUser()?->id,
            fileName: $this->upload->getClientOriginalName(),
            fileType: $this->upload->getMimeType(),
            rows: $parsed['rows'],
        );

        $this->rawUploadId = $upload->id;
        $this->headers = $parsed['headers'];
        $this->mapping = $this->guessMapping($parsed['headers']);
        $this->reset('upload');
        $this->step = 2;
    }

    public function toPreview(): void
    {
        abort_unless($this->entitled(), 403);

        if (($this->mapping['wine_name'] ?? '') === '') {
            $this->addError('mapping.wine_name', 'You must map the wine name column.');

            return;
        }

        $this->step = 3;
    }

    public function runImport(): void
    {
        abort_unless($this->entitled(), 403);
        abort_if($this->rawUploadId === null, 422);

        if (($this->mapping['wine_name'] ?? '') === '') {
            $this->step = 2;

            return;
        }

        $upload = (new RawUploadRepository)->find($this->rawUploadId);
        abort_if($upload === null, 404);

        // Only the uploader may import it, and only once (must be pending).
        $userId = (new UserRepository)->getLoggedInUser()?->id;
        abort_unless($upload->uploaded_by === $userId, 403);
        abort_unless($upload->status === 'pending', 422);

        $normalise = new NormaliseService;
        $upsertProduct = new UpsertProductAction;
        $mapping = $this->cleanMapping();

        $count = DB::transaction(function () use ($upload, $normalise, $upsertProduct, $mapping) {
            $imported = 0;

            foreach ($upload->rows ?? [] as $row) {
                $product = $normalise->toProductData($row, $mapping, $upload->supplier_id, $upload->id);

                if ($product === null) {
                    continue;
                }

                $upsertProduct->execute($product);
                $imported++;
            }

            (new MarkRawUploadImportedAction)->execute($upload->id, $mapping);

            if ($upload->supplier_id !== null) {
                (new SaveColumnMappingAction)->execute($upload->supplier_id, $mapping);
            }

            return $imported;
        });

        $this->importedCount = $count;
        $this->step = 4;
        $this->dispatch('toast', message: "Imported {$count} wines.");
    }

    public function restart(): void
    {
        $this->reset(['step', 'upload', 'rawUploadId', 'headers', 'mapping', 'importedCount']);
        $this->step = 1;
    }

    /**
     * @return array<string, string> only mappings with a chosen header
     */
    private function cleanMapping(): array
    {
        return array_filter($this->mapping, fn ($header) => $header !== '' && in_array($header, $this->headers, true));
    }

    /**
     * Best-effort auto-mapping from header names.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function guessMapping(array $headers): array
    {
        // Pre-fill from the supplier's previously saved mapping if present.
        $saved = $this->supplierId
            ? (new SupplierRepository)->find($this->supplierId)?->column_mapping
            : null;

        if (is_array($saved) && $saved !== []) {
            return array_intersect($saved, $headers);
        }

        $synonyms = [
            'wine_name' => ['wine', 'name', 'product', 'description', 'item'],
            'producer' => ['producer', 'estate', 'domaine', 'winery', 'maker'],
            'country' => ['country', 'origin'],
            'region' => ['region', 'appellation'],
            'sub_region' => ['subregion', 'sub region', 'sub-region', 'commune'],
            'grape' => ['grape', 'variety', 'varietal', 'cepage'],
            'colour' => ['colour', 'color', 'type', 'style'],
            'vintage' => ['vintage', 'year'],
            'format_ml' => ['format', 'size', 'bottle', 'volume', 'ml'],
            'case_size' => ['case', 'pack', 'units'],
            'unit_price' => ['price', 'cost', 'rate'],
            'stock' => ['stock', 'qty', 'quantity', 'available'],
        ];

        $mapping = [];

        foreach ($synonyms as $field => $needles) {
            foreach ($headers as $header) {
                $normalised = strtolower(trim($header));
                foreach ($needles as $needle) {
                    if (str_contains($normalised, $needle)) {
                        $mapping[$field] = $header;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    public function render()
    {
        $entitled = $this->entitled();
        $preview = [];

        if ($entitled && $this->step === 3 && $this->rawUploadId !== null) {
            $upload = (new RawUploadRepository)->find($this->rawUploadId);
            $normalise = new NormaliseService;
            $mapping = $this->cleanMapping();

            foreach (array_slice($upload->rows ?? [], 0, 8) as $row) {
                $product = $normalise->toProductData($row, $mapping);

                if ($product !== null) {
                    $preview[] = $product;
                }
            }
        }

        return view('livewire.import.index', [
            'entitled' => $entitled,
            'suppliers' => (new SupplierRepository)->all(),
            'fields' => self::FIELDS,
            'preview' => $preview,
        ]);
    }
}
