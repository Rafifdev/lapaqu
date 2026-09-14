<?php

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Staff Test',
        'subdomain' => 'resto-staff',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Pusat',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner Utama',
        'email' => 'owner@restostaff.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');

    $this->ownerToken = $this->owner->createToken('owner-test')->plainTextToken;

    $this->kasir = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Kasir 1',
        'email' => 'kasir@restostaff.test',
        'password' => 'secret123',
    ]);
    $this->kasir->assignRole('kasir');

    $this->kasirToken = $this->kasir->createToken('kasir-test')->plainTextToken;
});

test('owner can manage staff', function () {
    // 1. Owner creates new kitchen staff
    $createResponse = $this->withToken($this->ownerToken)->postJson('/api/staff', [
        'outlet_id' => $this->outlet->id,
        'name' => 'Chef Juna',
        'email' => 'chef@restostaff.test',
        'password' => 'ChefRahasia123',
        'role' => 'kitchen_staff',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('staff.name', 'Chef Juna');

    // 2. Owner lists staff
    $listResponse = $this->withToken($this->ownerToken)->getJson('/api/staff');
    $listResponse->assertOk()
        ->assertJsonCount(3, 'staff'); // Owner, Kasir, Chef
});

test('kasir is forbidden from managing staff', function () {
    $kasirForbidden = $this->withToken($this->kasirToken)->postJson('/api/staff', [
        'outlet_id' => $this->outlet->id,
        'name' => 'Staff Ilegal',
        'email' => 'illegal@restostaff.test',
        'password' => 'Rahasia123',
        'role' => 'kasir',
    ]);

    $kasirForbidden->assertStatus(403);
});

test('owner can manage outlets', function () {
    // 1. Create new branch outlet
    $createResponse = $this->withToken($this->ownerToken)->postJson('/api/outlets', [
        'name' => 'Cabang Bandung',
        'address' => 'Jl. Riau No. 10',
        'timezone' => 'Asia/Jakarta',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('outlet.name', 'Cabang Bandung');

    $outletId = $createResponse->json('outlet.id');

    // 2. List outlets
    $listResponse = $this->withToken($this->ownerToken)->getJson('/api/outlets');
    $listResponse->assertOk()
        ->assertJsonCount(2, 'outlets');

    // 3. Update outlet
    $updateResponse = $this->withToken($this->ownerToken)->putJson("/api/outlets/{$outletId}", [
        'name' => 'Cabang Bandung Dago',
    ]);
    $updateResponse->assertOk()
        ->assertJsonPath('outlet.name', 'Cabang Bandung Dago');
});
