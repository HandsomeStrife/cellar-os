<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

/**
 * Idempotent create-or-update keyed by the natural identity of a wine within
 * a supplier (name + vintage + format). Used by imports so re-uploading a
 * revised price list refreshes existing rows instead of duplicating them.
 */
class UpsertProductAction extends AbstractAction
{
    public function execute(ProductData $data, ?int $sourceDocumentId = null): ProductData
    {
        // Natural identity of a wine within a supplier. Producer is part of it:
        // a bare varietal name ("Chardonnay", "Riesling") is shared by many
        // producers, so keying on name+vintage+format alone collapses distinct
        // wines into one row (e.g. Ancre Hill's Chardonnay merging into another
        // grower's). A null producer matches other null-producer rows.
        $key = [
            'supplier_id' => $data->supplier_id,
            'wine_name' => $data->wine_name,
            'producer' => $data->producer,
            'vintage' => $data->vintage,
            'format_ml' => $data->format_ml,
        ];

        $existing = Product::where($key)->first();

        $attributes = [
            'raw_upload_id' => $data->raw_upload_id,
            'producer' => $data->producer,
            'country' => $data->country,
            'region' => $data->region,
            'sub_region' => $data->sub_region,
            'grape' => $data->grape,
            'colour' => $data->colour,
            'case_size' => $data->case_size,
            'sold_by' => $data->sold_by,
            'unit_price' => $data->unit_price,
            'pack_price' => $data->pack_price,
            'price_per_litre' => $data->price_per_litre,
            'stock' => $data->stock,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            // Listing lifecycle: an upserted wine is (still) listed, so it
            // un-archives if it had dropped out of an earlier edition. Golden
            // imports pass explicit values through to mirror the source env.
            'last_seen_at' => $data->last_seen_at ?? now(),
            'archived_at' => $data->archived_at,
        ];

        // Fill-don't-overwrite for descriptive attributes: a re-import (or an
        // edition whose format lists the same wine with a sparser row) must
        // never BLANK a value we already learned. A null incoming attribute
        // means "this source didn't say", not "this wine has no grape" — so we
        // keep the existing value. Prices/stock/lifecycle always take the new
        // value (those genuinely change edition to edition).
        if ($existing !== null) {
            foreach (['country', 'region', 'sub_region', 'grape', 'colour', 'latitude', 'longitude'] as $field) {
                if (($attributes[$field] ?? null) === null && $existing->{$field} !== null) {
                    $attributes[$field] = $existing->{$field};
                }
            }
        }

        // Document provenance is environment-local; only overwrite it when the
        // caller actually knows the edition this wine came from.
        if ($sourceDocumentId !== null) {
            $attributes['source_document_id'] = $sourceDocumentId;
        }

        $product = Product::updateOrCreate($key, $attributes);

        $result = $product->getData();

        // Every imported wine teaches the shared facts store (attributes only,
        // never prices) so sparser suppliers' lists can be gap-filled.
        (new ContributeWineFactsAction)->execute($result);

        return $result;
    }
}
