<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierShiftController extends Controller
{
    /**
     * Get active cashier shift for currently authenticated user/outlet
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $outletId = $request->query('outlet_id', $user->outlet_id);

        if (!$outletId) {
            return response()->json(['message' => 'Outlet ID tidak ditemukan.'], 400);
        }

        $shift = CashierShift::where('outlet_id', $outletId)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->with(['outlet', 'user'])
            ->latest('opened_at')
            ->first();

        return response()->json([
            'shift' => $shift,
            'is_open' => !is_null($shift),
        ]);
    }

    /**
     * Open a new shift directly without supervisor approval
     */
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'starting_cash' => ['required', 'numeric', 'min:0'],
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
            'device_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $outletId = $request->outlet_id ?? $user->outlet_id;

        if (!$outletId) {
            return response()->json(['message' => 'Outlet ID wajib ditentukan.'], 422);
        }

        // Check if there is already an open shift for this user & outlet
        $existingShift = CashierShift::where('outlet_id', $outletId)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return response()->json([
                'message' => 'Shift kasir Anda saat ini masih aktif.',
                'shift' => $existingShift,
            ], 200);
        }

        $startingCash = (float) $request->starting_cash;

        $shift = CashierShift::create([
            'tenant_id' => $user->tenant_id,
            'outlet_id' => $outletId,
            'user_id' => $user->id,
            'device_id' => $request->device_id,
            'starting_cash' => $startingCash,
            'cash_sales' => 0,
            'expected_cash' => $startingCash,
            'opened_at' => now(),
            'status' => 'open',
            'notes' => $request->notes,
        ]);

        $shift->load(['outlet', 'user']);

        return response()->json([
            'message' => 'Shift kasir berhasil dibuka.',
            'shift' => $shift,
        ], 201);
    }

    /**
     * Close an active shift
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $shift = CashierShift::where('tenant_id', $user->tenant_id)
            ->where('status', 'open')
            ->findOrFail($id);

        $actualCash = (float) $request->actual_cash;
        $difference = $actualCash - (float) $shift->expected_cash;

        $shift->update([
            'actual_cash' => $actualCash,
            'difference' => $difference,
            'closed_at' => now(),
            'status' => 'closed',
            'notes' => $request->notes ? ($shift->notes ? $shift->notes . "\n" . $request->notes : $request->notes) : $shift->notes,
        ]);

        return response()->json([
            'message' => 'Shift kasir berhasil ditutup.',
            'shift' => $shift,
        ]);
    }
}