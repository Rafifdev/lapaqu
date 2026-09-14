<?php
require __DIR__ . '/vendor/autoload.php';
\ = require_once __DIR__ . '/bootstrap/app.php';
\->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\ = App\Models\User::where('email', 'owner@kopisenopati.id')->first();
\ = App\Models\User::where('email', 'kasir@kopisenopati.id')->first();

echo " Owner outlet_id: \
