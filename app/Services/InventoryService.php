<?php

namespace App\Services;

use App\Enums\StockTransactionStatus;
use App\Enums\StockTransactionType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Post one stock transaction with many lines.
     *
     * $lines = [
     *   ['stock_location_id' => 1, 'product_variant_id' => 1, 'quantity_delta' => 10, 'unit_cost' => 200],
     * ]
     */
    public function post(
        StockTransactionType $type,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): StockTransaction {
        return DB::transaction(function () use ($type, $lines, $sourceType, $sourceId, $userId, $notes) {

            $transaction = StockTransaction::create([
                'transaction_number' => $this->nextNumber(),
                'transaction_type'   => $type,
                'status'             => StockTransactionStatus::Posted,
                'source_type'        => $sourceType,
                'source_id'          => $sourceId,
                'occurred_at'        => now(),
                'created_by'         => $userId,
                'posted_by'          => $userId,
                'posted_at'          => now(),
                'notes'              => $notes,
            ]);

            foreach ($lines as $i => $line) {
                $this->applyLine($transaction, $line, $i);
            }

            return $transaction->load('items');
        });
    }

    /** Reverse a posted transaction (creates a new opposite transaction). */
    public function reverse(StockTransaction $original, ?int $userId = null): StockTransaction
    {
        return DB::transaction(function () use ($original, $userId) {

            $original = StockTransaction::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($original->status !== StockTransactionStatus::Posted) {
                throw ValidationException::withMessages([
                    'transaction' => 'Only posted transactions can be reversed.',
                ]);
            }

            $reversal = StockTransaction::create([
                'transaction_number'         => $this->nextNumber(),
                'transaction_type'           => $original->transaction_type,
                'status'                     => StockTransactionStatus::Posted,
                'source_type'                => $original->source_type,
                'source_id'                  => $original->source_id,
                'occurred_at'                => now(),
                'created_by'                 => $userId,
                'posted_by'                  => $userId,
                'posted_at'                  => now(),
                'reversal_of_transaction_id' => $original->id,
                'notes'                      => 'Reversal of ' . $original->transaction_number,
            ]);

            foreach ($original->items as $i => $item) {
                $this->applyLine($reversal, [
                    'stock_location_id'  => $item->stock_location_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity_delta'     => -1 * (float) $item->quantity_delta,
                    'unit_cost'          => $item->unit_cost,
                ], $i);
            }

            $original->update(['status' => StockTransactionStatus::Reversed]);

            return $reversal->load('items');
        });
    }

    /** Stock you can still sell at a location. */
    public function available(int $locationId, int $variantId): float
    {
        $b = StockBalance::where('stock_location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->first();

        return $b ? (float) $b->quantity_on_hand - (float) $b->quantity_reserved : 0.0;
    }

    private function applyLine(StockTransaction $transaction, array $line, int $index): void
    {
        $delta = (float) $line['quantity_delta'];

        if ($delta == 0.0) {
            throw ValidationException::withMessages([
                "lines.$index.quantity_delta" => 'Quantity cannot be zero.',
            ]);
        }

        $variant = ProductVariant::with('product')->findOrFail($line['product_variant_id']);

        // Always write the ledger line
        $transaction->items()->create([
            'stock_location_id'  => $line['stock_location_id'],
            'product_variant_id' => $variant->id,
            'quantity_delta'     => $delta,
            'unit_cost'          => $line['unit_cost'] ?? null,
            'notes'              => $line['notes'] ?? null,
        ]);

        // Services or non-tracked products have no balance
        if (! $variant->product->track_inventory) {
            return;
        }

        // Get or create the balance row, then lock it
        StockBalance::firstOrCreate([
            'stock_location_id'  => $line['stock_location_id'],
            'product_variant_id' => $variant->id,
        ]);

        $balance = StockBalance::where('stock_location_id', $line['stock_location_id'])
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        $newQty = (float) $balance->quantity_on_hand + $delta;

        if ($newQty < 0) {
            throw ValidationException::withMessages([
                "lines.$index.quantity_delta" =>
                    "Not enough stock for SKU {$variant->sku}. On hand: {$balance->quantity_on_hand}.",
            ]);
        }

        $balance->update(['quantity_on_hand' => $newQty]);
    }

    private function nextNumber(): string
    {
        return 'STX-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}