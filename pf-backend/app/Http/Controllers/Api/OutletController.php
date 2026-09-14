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
        $outlets = Outlet::withCount(['tables', 'users'])->orderBy('name', 'asc')->get();

        return response()->json(['outlets' => $outlets]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
        ]);

        $outlet = Outlet::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'timezone' => $request->input('timezone', 'Asia/Jakarta'),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Outlet cabang baru berhasil ditambahkan.',
            'outlet' => $outlet,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $outlet->update($request->only(['name', 'address', 'phone', 'timezone', 'is_active']));

        return response()->json([
            'message' => 'Data outlet cabang berhasil diperbarui.',
            'outlet' => $outlet,
        ]);
    }
}
