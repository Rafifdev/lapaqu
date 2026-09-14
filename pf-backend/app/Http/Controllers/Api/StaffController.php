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
        $outletId = $request->query('outlet_id', $request->user()->outlet_id);

        $query = User::with(['roles', 'outlet']);
        
        // Scope to tenant if tenant_id exists
        if ($request->user()->tenant_id) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $staff = $query->orderBy('name', 'asc')->get()->map(function ($user) {
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
                'created_at' => $user->created_at,
            ];
        });

        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auto-resolve outlet_id if not explicitly provided
        $outletId = $request->input('outlet_id') ?: $request->user()->outlet_id;
        if (!$outletId && $request->user()->tenant_id) {
            $outletId = Outlet::where('tenant_id', $request->user()->tenant_id)->first()?->id;
        }

        if ($outletId) {
            $request->merge(['outlet_id' => $outletId]);
        }

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', 'in:kasir,kitchen_staff,owner'],
        ]);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'outlet_id' => $request->outlet_id,
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => true,
        ]);

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
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($request->user()->tenant_id && $user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'Tidak diizinkan mengubah staf tenant lain.'], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['sometimes', 'required', 'string', 'in:kasir,kitchen_staff,owner'],
        ]);

        $updateData = $request->only(['name', 'email', 'phone', 'is_active']);
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
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
            ],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()?->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        if ($request->user()->tenant_id && $user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Akun staf berhasil dinonaktifkan / dihapus.']);
    }
}
