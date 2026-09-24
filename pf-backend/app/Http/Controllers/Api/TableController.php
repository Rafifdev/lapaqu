<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Outlet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id);

        $outlet = null;
        if ($outletId) {
            $outlet = Outlet::withoutGlobalScopes()->find($outletId);
        }
        if (!$outlet && $request->user()?->tenant_id) {
            $outlet = Outlet::withoutGlobalScopes()->where('tenant_id', $request->user()->tenant_id)->first();
            $outletId = $outlet?->id;
        }
        if (!$outlet) {
            $outlet = Outlet::withoutGlobalScopes()->whereHas('tables')->first()
                   ?: Outlet::withoutGlobalScopes()->first();
            $outletId = $outlet?->id;
        }

        $tenantId = $outlet?->tenant_id ?: ($request->user()?->tenant_id ?: (app()->bound('tenant_id') ? app('tenant_id') : null));

        $query = Table::withoutGlobalScopes();
        if ($outletId) {
            $hasOutletTables = Table::withoutGlobalScopes()->where('outlet_id', $outletId)->exists();
            if ($hasOutletTables) {
                $query->where('outlet_id', $outletId);
            } elseif ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $tables = $query->orderBy('table_number', 'asc')
            ->with(['activeSession'])
            ->get();

        return response()->json(['tables' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'table_number' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $exists = Table::where('outlet_id', $request->outlet_id)
            ->where('table_number', $request->table_number)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Nomor meja sudah ada di outlet ini.'], 422);
        }

        $table = Table::create([
            'outlet_id' => $request->outlet_id,
            'table_number' => $request->table_number,
            'capacity' => $request->input('capacity', 4),
            'qr_code_token' => Str::random(32),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Meja berhasil ditambahkan.',
            'table' => $table,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        $request->validate([
            'table_number' => ['sometimes', 'required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->has('table_number') && $request->table_number !== $table->table_number) {
            $exists = Table::where('outlet_id', $table->outlet_id)
                ->where('table_number', $request->table_number)
                ->where('id', '!=', $table->id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Nomor meja sudah digunakan di outlet ini.'], 422);
            }
        }

        $table->update($request->only(['table_number', 'capacity', 'is_active']));

        return response()->json([
            'message' => 'Meja berhasil diperbarui.',
            'table' => $table,
        ]);
    }

    public function regenerateQr(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $table->qr_code_token = Str::random(32);
        $table->save();

        return response()->json([
            'message' => 'QR Code token berhasil diperbarui.',
            'table' => $table,
        ]);
    }

    public function qrCodeSvg(string $id): Response
    {
        $table = Table::with('outlet.tenant')->findOrFail($id);
        
        $customerOrderUrl = config('app.url', 'http://localhost') . '/order/' . $table->qr_code_token;

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svgString = $writer->writeString($customerOrderUrl);

        return response($svgString, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $table->table_number . '.svg"',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $table->delete();

        return response()->json(['message' => 'Meja berhasil dihapus.']);
    }
}
