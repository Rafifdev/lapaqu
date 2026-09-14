<?php

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Notif Test',
        'subdomain' => 'resto-notif',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Notif',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner Notif',
        'email' => 'owner@notif.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');
    $this->token = $this->owner->createToken('notif-token')->plainTextToken;
});

test('user can view and mark in-app notifications as read', function () {
    $subscription = \App\Models\Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => \App\Models\Plan::where('code', 'basic')->first()->id,
        'status' => 'trial',
        'active_outlets_count' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addDays(3),
        'next_billing_date' => now()->addDays(3),
    ]);

    // Send notification to owner
    $this->owner->notify(new SubscriptionExpiringNotification($subscription, 3));

    // 1. Get notifications
    $listResponse = $this->withToken($this->token)->getJson('/api/notifications');
    $listResponse->assertOk()
        ->assertJsonPath('unread_count', 1);

    $notifId = $listResponse->json('notifications.data.0.id');

    // 2. Mark as read
    $readResponse = $this->withToken($this->token)->patchJson("/api/notifications/{$notifId}/read");
    $readResponse->assertOk();

    expect($this->owner->unreadNotifications()->count())->toBe(0);
});
