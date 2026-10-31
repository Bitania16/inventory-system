<?php

namespace App\Http\Controllers\Api;

use App\Enums\CashierShiftStatus;
use App\Enums\CashMovementType;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => CashierShift::with(['register', 'opener'])->latest()->paginate(15),
        ]);
    }

    public function show(CashierShift $shift): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $shift->load(['register', 'opener', 'closer']),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pos_register_id' => 'required|exists:pos_registers,id',
            'opening_cash'    => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        $shift = DB::transaction(function () use ($data, $request) {

            $alreadyOpen = CashierShift::where('pos_register_id', $data['pos_register_id'])
                ->where('status', CashierShiftStatus::Open->value)
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'pos_register_id' => 'This register already has an open shift.',
                ]);
            }

            $shift = CashierShift::create([
                'pos_register_id' => $data['pos_register_id'],
                'opened_by'       => $request->user()->id,
                'status'          => CashierShiftStatus::Open,
                'opening_cash'    => $data['opening_cash'],
                'opened_at'       => now(),
                'notes'           => $data['notes'] ?? null,
            ]);

            $shift->cashMovements()->create([
                'movement_type' => CashMovementType::OpeningFloat,
                'amount'        => $data['opening_cash'],
                'reason'        => 'Opening float',
                'recorded_by'   => $request->user()->id,
                'occurred_at'   => now(),
            ]);

            return $shift;
        });

        return response()->json(['success' => true, 'message' => 'Shift opened.', 'data' => $shift], 201);
    }

    public function cashMovement(Request $request, CashierShift $shift): JsonResponse
    {
        $data = $request->validate([
            'movement_type' => 'required|in:cash_in,cash_out',
            'amount'        => 'required|numeric|gt:0',
            'reason'        => 'required|string|max:255',
        ]);

        if ($shift->status !== CashierShiftStatus::Open) {
            throw ValidationException::withMessages(['shift' => 'Shift is not open.']);
        }

        $movement = $shift->cashMovements()->create([
            'movement_type' => $data['movement_type'],
            'amount'        => $data['amount'],
            'reason'        => $data['reason'],
            'recorded_by'   => $request->user()->id,
            'occurred_at'   => now(),
        ]);

        return response()->json(['success' => true, 'data' => $movement], 201);
    }

    public function close(Request $request, CashierShift $shift): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'notes'        => 'nullable|string',
        ]);

        $shift = DB::transaction(function () use ($data, $request, $shift) {

            $shift = CashierShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($shift->status !== CashierShiftStatus::Open) {
                throw ValidationException::withMessages(['shift' => 'Shift is not open.']);
            }

            // Expected = opening float + cash in - cash out
            $in = (float) $shift->cashMovements()
                ->whereIn('movement_type', [
                    CashMovementType::OpeningFloat->value,
                    CashMovementType::CashIn->value,
                ])->sum('amount');

            $out = (float) $shift->cashMovements()
                ->where('movement_type', CashMovementType::CashOut->value)
                ->sum('amount');

            $expected = $in - $out;

            $shift->update([
                'status'          => CashierShiftStatus::Closed,
                'closed_by'       => $request->user()->id,
                'closed_at'       => now(),
                'counted_cash'    => $data['counted_cash'],
                'expected_cash'   => $expected,
                'variance_amount' => (float) $data['counted_cash'] - $expected,
                'notes'           => $data['notes'] ?? $shift->notes,
            ]);

            return $shift;
        });

        return response()->json(['success' => true, 'message' => 'Shift closed.', 'data' => $shift]);
    }
}