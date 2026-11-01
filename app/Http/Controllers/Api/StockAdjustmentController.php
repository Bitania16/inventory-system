<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockAdjustmentStatus;
use App\Enums\StockTransactionType;
use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    private function code(string $p): string
    {
        return $p . '-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public function adjustments(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => StockAdjustment::with('items')->latest()->paginate(15)]);
    }

    public function createAdjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stock_location_id'          => 'required|exists:stock_locations,id',
            'reason'                     => 'nullable|string|max:255',
            'notes'                      => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.counted_quantity'   => 'required|numeric|min:0',
        ]);

        $adj = DB::transaction(function () use ($data, $request) {
            $adj = StockAdjustment::create([
                'adjustment_number' => $this->code('ADJ'),
                'stock_location_id' => $data['stock_location_id'],
                'status'            => StockAdjustmentStatus::Draft,
                'reason'            => $data['reason'] ?? null,
                'created_by'        => $request->user()->id,
                'counted_at'        => now(),
                'notes'             => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $system = (float) (StockBalance::where('stock_location_id', $data['stock_location_id'])
                    ->where('product_variant_id', $row['product_variant_id'])->value('quantity_on_hand') ?? 0);

                $adj->items()->create([
                    'product_variant_id'  => $row['product_variant_id'],
                    'system_quantity'     => $system,
                    'counted_quantity'    => $row['counted_quantity'],
                    'difference_quantity' => (float) $row['counted_quantity'] - $system,
                ]);
            }

            return $adj;
        });

        return response()->json(['success' => true, 'message' => 'Adjustment created.', 'data' => $adj->load('items')], 201);
    }

    public function postAdjustment(Request $request, StockAdjustment $adjustment): JsonResponse
    {
        DB::transaction(function () use ($request, $adjustment) {
            $adjustment = StockAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status !== StockAdjustmentStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft adjustments can be posted.']);
            }

            $in = $out = [];
            foreach ($adjustment->items as $item) {
                $diff = (float) $item->difference_quantity;
                if ($diff == 0.0) continue;

                $line = [
                    'stock_location_id'  => $adjustment->stock_location_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity_delta'     => $diff,
                    'unit_cost'          => $item->unit_cost,
                ];
                $diff > 0 ? $in[] = $line : $out[] = $line;
            }

            if ($in)  $this->inventory->post(StockTransactionType::AdjustmentIn, $in, 'stock_adjustment', $adjustment->id, $request->user()->id);
            if ($out) $this->inventory->post(StockTransactionType::AdjustmentOut, $out, 'stock_adjustment', $adjustment->id, $request->user()->id);

            $adjustment->update([
                'status'      => StockAdjustmentStatus::Posted,
                'approved_by' => $request->user()->id,
                'posted_by'   => $request->user()->id,
                'posted_at'   => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Adjustment posted. Stock updated.', 'data' => $adjustment->fresh('items')]);
    }

    public function transfers(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => StockTransfer::with('items')->latest()->paginate(15)]);
    }

    public function createTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_stock_location_id'     => 'required|exists:stock_locations,id',
            'to_stock_location_id'       => 'required|exists:stock_locations,id|different:from_stock_location_id',
            'notes'                      => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity'           => 'required|numeric|gt:0',
        ]);

        $t = DB::transaction(function () use ($data, $request) {
            $t = StockTransfer::create([
                'transfer_number'        => $this->code('TRF'),
                'from_stock_location_id' => $data['from_stock_location_id'],
                'to_stock_location_id'   => $data['to_stock_location_id'],
                'status'                 => StockTransferStatus::Requested,
                'requested_by'           => $request->user()->id,
                'requested_at'           => now(),
                'notes'                  => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $t->items()->create([
                    'product_variant_id' => $row['product_variant_id'],
                    'quantity_requested' => $row['quantity'],
                ]);
            }
            return $t;
        });

        return response()->json(['success' => true, 'message' => 'Transfer requested.', 'data' => $t->load('items')], 201);
    }

    public function shipTransfer(Request $request, StockTransfer $transfer): JsonResponse
    {
        DB::transaction(function () use ($request, $transfer) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($transfer->status, [StockTransferStatus::Requested, StockTransferStatus::Approved], true)) {
                throw ValidationException::withMessages(['status' => 'Transfer cannot be shipped in its current status.']);
            }

            $lines = [];
            foreach ($transfer->items as $item) {
                $lines[] = [
                    'stock_location_id'  => $transfer->from_stock_location_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity_delta'     => -1 * (float) $item->quantity_requested,
                ];
                $item->update(['quantity_shipped' => $item->quantity_requested]);
            }

            $this->inventory->post(StockTransactionType::TransferOut, $lines, 'stock_transfer', $transfer->id, $request->user()->id);

            $transfer->update([
                'status'      => StockTransferStatus::InTransit,
                'approved_by' => $request->user()->id,
                'shipped_by'  => $request->user()->id,
                'shipped_at'  => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Transfer shipped.', 'data' => $transfer->fresh('items')]);
    }

    public function receiveTransfer(Request $request, StockTransfer $transfer): JsonResponse
    {
        DB::transaction(function () use ($request, $transfer) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== StockTransferStatus::InTransit) {
                throw ValidationException::withMessages(['status' => 'Only in-transit transfers can be received.']);
            }

            $lines = [];
            foreach ($transfer->items as $item) {
                $lines[] = [
                    'stock_location_id'  => $transfer->to_stock_location_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity_delta'     => (float) $item->quantity_shipped,
                ];
                $item->update(['quantity_received' => $item->quantity_shipped]);
            }

            $this->inventory->post(StockTransactionType::TransferIn, $lines, 'stock_transfer', $transfer->id, $request->user()->id);

            $transfer->update([
                'status'      => StockTransferStatus::Received,
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Transfer received.', 'data' => $transfer->fresh('items')]);
    }
}