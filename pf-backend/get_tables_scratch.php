<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = App\Models\Table::all(['id', 'table_number', 'qr_code_token', 'outlet_id']);
echo $tables->toJson(JSON_PRETTY_PRINT);
