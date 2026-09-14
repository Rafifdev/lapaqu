<?php

$mailHost = 'smtp-relay.brevo.com';
$mailPort = 587;
$mailUser = 'b712a3001@smtp-brevo.com';
$mailPass = env('MAIL_PASSWORD');
$mailFrom = 'tolebot1@gmail.com';
$mailFromName = 'Lapaqu Platform';
$mailEncryption = 'tls';

// Read directly from .env file to guarantee 100% freshness even if PHP process environment is stale
$envFile = base_path('.env');
if (file_exists($envFile)) {
    try {
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if ($k === 'MAIL_HOST' && !empty($v)) $mailHost = $v;
            if ($k === 'MAIL_PORT' && !empty($v)) $mailPort = (int) $v;
            if ($k === 'MAIL_USERNAME' && !empty($v)) $mailUser = $v;
            if ($k === 'MAIL_PASSWORD' && !empty($v)) $mailPass = $v;
            if ($k === 'MAIL_FROM_ADDRESS' && !empty($v)) $mailFrom = $v;
            if ($k === 'MAIL_FROM_NAME' && !empty($v)) $mailFromName = $v;
            if ($k === 'MAIL_ENCRYPTION' && !empty($v)) $mailEncryption = $v;
        }
    } catch (\Throwable $e) {
        // fallback
    }
}

if ($mailHost === '127.0.0.1' || $mailHost === 'localhost') {
    $mailHost = 'smtp-relay.brevo.com';
}
if ($mailPort === 2525 || $mailPort === 0) {
    $mailPort = 587;
}

return [

    'default' => 'smtp',

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => null,
            'url' => null,
            'host' => $mailHost,
            'port' => $mailPort,
            'username' => $mailUser,
            'password' => $mailPass,
            'encryption' => $mailEncryption,
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => $mailFrom,
        'name' => $mailFromName,
    ],

];
