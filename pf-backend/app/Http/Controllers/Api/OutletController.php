<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outlets = Outlet::with('tenant')->withCount(['tables', 'users'])
            ->orderBy('is_main', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($outlets->isNotEmpty() && !$outlets->contains('is_main', true)) {
            $first = $outlets->first();
            $first->update(['is_main' => true]);
            $first->is_main = true;
        }

        return response()->json(['outlets' => $outlets]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'is_main' => ['nullable', 'boolean'],
        ]);

        $existingCount = Outlet::count();
        $isMain = $existingCount === 0 || (bool) $request->input('is_main', false);

        if ($isMain) {
            Outlet::where('is_main', true)->update(['is_main' => false]);
        }

        $outlet = Outlet::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'timezone' => $request->input('timezone', 'Asia/Jakarta'),
            'is_active' => true,
            'is_main' => $isMain,
        ]);

        $this->seedOutletStarterData($outlet);

        return response()->json([
            'message' => 'Outlet cabang baru berhasil ditambahkan.',
            'outlet' => $outlet,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::find($id);
        if (!$outlet) {
            $outlet = Outlet::where('is_main', true)->first() ?: Outlet::first();
            if (!$outlet) {
                return response()->json(['message' => 'Outlet tidak ditemukan.'], 404);
            }
        }

        $request->validate([
            'tenant_name' => ['nullable', 'string', 'max:150'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'is_active' => ['nullable', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
            'enable_tax' => ['nullable', 'boolean'],
            'tax_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enable_service_charge' => ['nullable', 'boolean'],
            'service_charge_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'table_timeout' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'logo_url' => ['nullable', 'string'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $data = $request->only([
            'name',
            'slogan',
            'address',
            'phone',
            'timezone',
            'is_active',
            'enable_tax',
            'tax_percentage',
            'enable_service_charge',
            'service_charge_percentage',
            'table_timeout',
        ]);

        if ($request->has('logo_url')) {
            $data['logo_url'] = $request->logo_url;
            if ($outlet->tenant) {
                $outlet->tenant->update(['logo_url' => $request->logo_url]);
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo_url'] = asset('storage/' . $path);
            if ($outlet->tenant) {
                $outlet->tenant->update(['logo_url' => $data['logo_url']]);
            }
        }

        if ($request->filled('tenant_name') && $outlet->tenant) {
            $outlet->tenant->update(['name' => trim((string) $request->tenant_name)]);
        }

        if ($request->filled('subdomain') && $outlet->tenant) {
            $subdomain = strtolower($request->subdomain);
            $exists = \App\Models\Tenant::where('subdomain', $subdomain)->where('id', '!=', $outlet->tenant_id)->exists();
            if (!$exists) {
                $outlet->tenant->update(['subdomain' => $subdomain]);
            }
        }

        if ($request->has('is_main')) {
            $wantsMain = (bool) $request->input('is_main');
            if ($wantsMain) {
                Outlet::where('id', '!=', $outlet->id)->where('is_main', true)->update(['is_main' => false]);
                $data['is_main'] = true;
            } else {
                $otherMain = Outlet::where('id', '!=', $outlet->id)->where('is_main', true)->exists();
                if (!$otherMain) {
                    $other = Outlet::where('id', '!=', $outlet->id)->orderBy('created_at', 'asc')->first();
                    if ($other) {
                        $other->update(['is_main' => true]);
                        $data['is_main'] = false;
                    } else {
                        $data['is_main'] = true;
                    }
                } else {
                    $data['is_main'] = false;
                }
            }
        }

        $outlet->update($data);
        $outlet->load('tenant');

        return response()->json([
            'message' => 'Data outlet cabang berhasil diperbarui.',
            'outlet' => $outlet,
        ]);
    }
    public function destroy(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $outletCount = Outlet::count();
        if ($outletCount <= 1) {
            return response()->json([
                'message' => 'Tidak dapat menghapus satu-satunya outlet cabang yang Anda miliki.',
            ], 422);
        }

        $wasMain = $outlet->is_main;
        $outlet->delete();

        if ($wasMain) {
            $newMain = Outlet::orderBy('created_at', 'asc')->first();
            if ($newMain) {
                $newMain->update(['is_main' => true]);
            }
        }

        return response()->json([
            'message' => 'Outlet cabang berhasil dihapus.',
        ]);
    }

    public function generatePairingCode(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        // Expire any existing active codes for this outlet
        \App\Models\OutletPairingCode::where('outlet_id', $outlet->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Generate unique 6-character uppercase alphanumeric code
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(6));
        } while (\App\Models\OutletPairingCode::where('code', $code)->where('status', 'active')->exists());

        $pairingCode = \App\Models\OutletPairingCode::create([
            'outlet_id' => $outlet->id,
            'created_by' => $request->user()?->id,
            'code' => $code,
            'status' => 'active',
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'message' => 'Kode pairing outlet berhasil dibuat.',
            'pairing_code' => $pairingCode->code,
            'expires_at' => $pairingCode->expires_at->toIso8601String(),
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ],
        ]);
    }

    public function getActivePairingCode(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $activeCode = \App\Models\OutletPairingCode::where('outlet_id', $outlet->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return response()->json([
            'has_active_code' => (bool) $activeCode,
            'pairing_code' => $activeCode?->code,
            'expires_at' => $activeCode?->expires_at?->toIso8601String(),
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
            ],
        ]);
    }

    public function getConnectedDevices(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $roleOrder = [
            'kasir' => 1,
            'kitchen_staff' => 2,
            'store_manager' => 3,
        ];

        $staffUsers = \App\Models\User::withoutGlobalScopes()
            ->where('outlet_id', $outlet->id)
            ->role(['kasir', 'kitchen_staff', 'store_manager'])
            ->with('roles')
            ->get()
            ->sortBy(function ($u) use ($roleOrder) {
                $role = $u->roles->pluck('name')->first() ?? 'kasir';
                return $roleOrder[$role] ?? 99;
            })
            ->values();

        $devices = \App\Models\OutletPairingCode::where('outlet_id', $outlet->id)
            ->where('status', 'used')
            ->orderByDesc('used_at')
            ->get()
            ->values()
            ->map(function ($p, $index) use ($staffUsers, $outlet) {
                $name = $p->device_name ?: 'Perangkat Kasir';
                if (str_contains($name, 'Mozilla/')) {
                    if (str_contains($name, 'Windows')) $name = 'Windows PC (Kasir)';
                    elseif (str_contains($name, 'Macintosh')) $name = 'Mac (Kasir)';
                    elseif (str_contains($name, 'Android')) $name = 'Android Tablet (Kasir)';
                    elseif (str_contains($name, 'iPad')) $name = 'iPad POS (Kasir)';
                    elseif (str_contains($name, 'iPhone')) $name = 'iPhone POS (Kasir)';
                    else $name = 'Web Browser (Kasir)';
                }

                $assignedStaff = $staffUsers->isNotEmpty()
                    ? $staffUsers->get($index % $staffUsers->count())
                    : null;

                $staffName = $assignedStaff?->name;
                $roleName = $assignedStaff?->roles->pluck('name')->first();

                $roleLabel = match ($roleName) {
                    'store_manager' => 'Manager',
                    'kitchen_staff' => 'Dapur',
                    'kasir' => 'Kasir',
                    default => 'Staff'
                };

                $userId = $assignedStaff?->id ?? $p->created_by ?? $p->id;
                $userName = $staffName ?: ($name ?: "Kasir {$outlet->name}");

                return [
                    'id' => $p->id,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'staff_name' => $staffName,
                    'role' => $roleLabel,
                    'code' => $p->code,
                    'device_name' => $name,
                    'status' => 'connected',
                    'connected_at' => $p->used_at ? \Carbon\Carbon::parse($p->used_at)->format('d/m/y') : '-',
                ];
            });

        return response()->json([
            'devices' => $devices,
        ]);
    }

    public function disconnectDevice(Request $request, string $id, string $deviceId): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $pairing = \App\Models\OutletPairingCode::where('outlet_id', $outlet->id)
            ->where('id', $deviceId)
            ->firstOrFail();

        $pairing->update([
            'status' => 'expired',
        ]);

        return response()->json([
            'message' => 'Perangkat berhasil diputuskan.',
        ]);
    }

    public function uploadLogo(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::find($id);
        if (!$outlet) {
            $outlet = Outlet::where('is_main', true)->first() ?: Outlet::first();
            if (!$outlet) {
                return response()->json(['message' => 'Outlet tidak ditemukan.'], 404);
            }
        }

        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('logos', 'public');
        $logoUrl = asset('storage/' . $path);

        $outlet->update(['logo_url' => $logoUrl]);
        if ($outlet->tenant) {
            $outlet->tenant->update(['logo_url' => $logoUrl]);
        }

        return response()->json([
            'message' => 'Logo restoran berhasil diperbarui.',
            'logo_url' => $logoUrl,
            'outlet' => $outlet->load('tenant'),
        ]);
    }

    protected function seedOutletStarterData(Outlet $outlet): void
    {
        for ($n = 1; $n <= 5; $n++) {
            $numStr = sprintf('%02d', $n);
            \App\Models\Table::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $outlet->tenant_id, 'outlet_id' => $outlet->id, 'table_number' => "Meja {$numStr}"],
                [
                    'capacity' => ($n % 2 == 0) ? 4 : 2,
                    'qr_code_token' => "qr_{$outlet->id}_{$numStr}",
                    'is_active' => true,
                ]
            );
        }

        $sourceOutlet = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $outlet->tenant_id)
            ->where('id', '!=', $outlet->id)
            ->whereHas('menuItems')
            ->first() ?: Outlet::withoutGlobalScopes()->whereHas('menuItems')->first();

        if ($sourceOutlet) {
            $categories = \App\Models\MenuCategory::withoutGlobalScopes()->where('outlet_id', $sourceOutlet->id)->get();
            $catMap = [];
            foreach ($categories as $cat) {
                $newCat = \App\Models\MenuCategory::withoutGlobalScopes()->create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'name' => $cat->name,
                    'sort_order' => $cat->sort_order,
                    'is_active' => $cat->is_active,
                ]);
                $catMap[$cat->id] = $newCat->id;
            }

            $items = \App\Models\MenuItem::withoutGlobalScopes()->with(['variantGroups.options'])->where('outlet_id', $sourceOutlet->id)->get();
            foreach ($items as $item) {
                $newCatId = $catMap[$item->category_id] ?? null;
                if (!$newCatId) continue;

                $newItem = \App\Models\MenuItem::withoutGlobalScopes()->create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'category_id' => $newCatId,
                    'name' => $item->name,
                    'description' => $item->description,
                    'base_price' => $item->base_price,
                    'image_url' => $item->image_url,
                    'is_available' => true,
                ]);

                foreach ($item->variantGroups as $vg) {
                    $newVg = \App\Models\MenuItemVariantGroup::withoutGlobalScopes()->create([
                        'menu_item_id' => $newItem->id,
                        'name' => $vg->name,
                        'is_required' => $vg->is_required,
                        'min_selection' => $vg->min_selection,
                        'max_selection' => $vg->max_selection,
                    ]);
                    foreach ($vg->options as $opt) {
                        \App\Models\MenuItemVariantOption::withoutGlobalScopes()->create([
                            'variant_group_id' => $newVg->id,
                            'name' => $opt->name,
                            'price_modifier' => $opt->price_modifier,
                            'is_available' => $opt->is_available,
                        ]);
                    }
                }
            }
        }
    }

}
