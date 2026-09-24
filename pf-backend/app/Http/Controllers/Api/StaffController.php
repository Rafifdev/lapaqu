<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        
        // Store Manager is strictly locked to their own outlet
        if ($currentUser->hasRole('store_manager')) {
            $outletId = $currentUser->outlet_id;
        } else {
            // Owner & Superadmin view all tenant staff by default unless specific outlet_id query param is provided
            $outletId = $request->query('outlet_id');
        }

        $query = User::with(['roles', 'outlet']);
        
        // Scope to tenant if tenant_id exists
        if ($currentUser->tenant_id) {
            $query->where('tenant_id', $currentUser->tenant_id);
        }

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Role Owner tidak masuk ke data manager, owner hanya masuk ke staff & role di role owner sendiri
        if (!$currentUser->hasRole('owner') && !$currentUser->hasRole('superadmin')) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'owner');
            });
        }

        $staffCollection = $query->orderBy('name', 'asc')->get();

        // Khusus di role manager: store manager selalu jadi primary nomor 1 teratas
        if ($currentUser->hasRole('store_manager')) {
            $staffCollection = $staffCollection->sortByDesc(function ($user) {
                return $user->hasRole('store_manager') ? 1 : 0;
            })->values();
        }

        $staff = $staffCollection->map(function ($user) {
            $roleName = $user->roles->first()?->name ?? 'kasir';
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'outlet' => $user->outlet ? ['id' => $user->outlet->id, 'name' => $user->outlet->name] : null,
                'outlet_id' => $user->outlet_id,
                'roles' => $user->getRoleNames(),
                'role' => $roleName,
                'is_active' => (bool) $user->is_active,
                'has_pin' => !empty($user->pin),
                'created_at' => $user->created_at,
            ];
        });

        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Auto-resolve outlet_id if not explicitly provided
        $outletId = $request->input('outlet_id') ?: $currentUser->outlet_id;
        if (!$outletId && $currentUser->tenant_id) {
            $outletId = Outlet::where('tenant_id', $currentUser->tenant_id)->first()?->id;
        }

        // Store Manager can only add staff for their own outlet
        if ($currentUser->hasRole('store_manager')) {
            $outletId = $currentUser->outlet_id;
        }

        if ($outletId) {
            $request->merge(['outlet_id' => $outletId]);
        }

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'pin' => ['nullable', 'string', 'digits:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', 'in:kasir,kitchen_staff,store_manager,owner'],
        ]);

        // Store Manager hierarchy restriction
        if ($currentUser->hasRole('store_manager') && !in_array($request->role, ['kasir', 'kitchen_staff'])) {
            return response()->json([
                'message' => 'Store Manager hanya memiliki wewenang mengelola staf kasir dan kitchen.'
            ], 403);
        }

        $email = $request->filled('email')
            ? strtolower($request->email)
            : 'staff_' . Str::slug($request->name) . '_' . Str::lower(Str::random(6)) . '@pos.local';

        $password = $request->filled('password')
            ? Hash::make($request->password)
            : Hash::make(Str::random(16));

        $userData = [
            'tenant_id' => $currentUser->tenant_id,
            'outlet_id' => $request->outlet_id,
            'name' => $request->name,
            'email' => $email,
            'password' => $password,
            'phone' => $request->phone,
            'is_active' => true,
        ];
        if ($request->filled('pin')) {
            $userData['pin'] = Hash::make($request->pin);
        }
        $user = User::create($userData);

        $user->assignRole($request->role);
        $user->load(['roles', 'outlet']);

        return response()->json([
            'message' => 'Akun staf berhasil dibuat.',
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'outlet' => $user->outlet ? ['id' => $user->outlet->id, 'name' => $user->outlet->name] : null,
                'outlet_id' => $user->outlet_id,
                'roles' => $user->getRoleNames(),
                'role' => $request->role,
                'is_active' => (bool) $user->is_active,
                'has_pin' => !empty($user->pin),
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::with(['roles', 'outlet'])->findOrFail($id);
        $roleName = $user->roles->first()?->name ?? 'kasir';

        return response()->json([
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'outlet' => $user->outlet ? ['id' => $user->outlet->id, 'name' => $user->outlet->name] : null,
                'outlet_id' => $user->outlet_id,
                'roles' => $user->getRoleNames(),
                'role' => $roleName,
                'is_active' => (bool) $user->is_active,
                'has_pin' => !empty($user->pin),
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($currentUser->tenant_id && $user->tenant_id !== $currentUser->tenant_id) {
            return response()->json(['message' => 'Tidak diizinkan mengubah staf tenant lain.'], 403);
        }

        // Store manager cannot modify owner or other store managers
        if ($currentUser->hasRole('store_manager')) {
            if ($user->hasRole('owner') || $user->hasRole('store_manager') || $user->outlet_id !== $currentUser->outlet_id) {
                return response()->json(['message' => 'Tidak diizinkan mengubah akun peran ini.'], 403);
            }
        }

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'pin' => ['nullable', 'string', 'digits:6'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['sometimes', 'required', 'string', 'in:kasir,kitchen_staff,store_manager,owner'],
        ]);

        if ($currentUser->hasRole('store_manager') && $request->filled('role') && !in_array($request->role, ['kasir', 'kitchen_staff'])) {
            return response()->json([
                'message' => 'Store Manager hanya memiliki wewenang mengelola staf kasir dan kitchen.'
            ], 403);
        }

        $updateData = $request->only(['name', 'email', 'phone', 'is_active']);
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }
        if ($request->has('pin')) {
            $updateData['pin'] = $request->filled('pin') ? Hash::make($request->pin) : null;
        }

        $user->update($updateData);

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        $user->load(['roles', 'outlet']);
        $roleName = $user->roles->first()?->name ?? 'kasir';

        return response()->json([
            'message' => 'Data staf berhasil diperbarui.',
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'outlet' => $user->outlet ? ['id' => $user->outlet->id, 'name' => $user->outlet->name] : null,
                'outlet_id' => $user->outlet_id,
                'roles' => $user->getRoleNames(),
                'role' => $roleName,
                'is_active' => (bool) $user->is_active,
                'has_pin' => !empty($user->pin),
            ],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($user->id === $currentUser?->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        if ($currentUser->tenant_id && $user->tenant_id !== $currentUser->tenant_id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        // Store manager cannot delete owner or store manager
        if ($currentUser->hasRole('store_manager')) {
            if ($user->hasRole('owner') || $user->hasRole('store_manager') || $user->outlet_id !== $currentUser->outlet_id) {
                return response()->json(['message' => 'Tidak diizinkan menghapus akun ini.'], 403);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Akun staf berhasil dinonaktifkan / dihapus.']);
    }
}
