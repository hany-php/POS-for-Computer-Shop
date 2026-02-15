<?php
require_once __DIR__ . '/includes/bootstrap.php';

$settings = getStoreSettings();
$appName = trim((string)($settings['store_name'] ?? APP_NAME));
if ($appName === '') {
    $appName = APP_NAME;
}

$manifest = [
    'name' => $appName,
    'short_name' => mb_substr($appName, 0, 12),
    'description' => 'نظام نقطة بيع وصيانة لمحل الكمبيوتر',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#f6f7f8',
    'theme_color' => '#137fec',
    'lang' => 'ar',
    'dir' => 'rtl',
    'icons' => [
        [
            'src' => 'assets/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/pwa/icon-maskable-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]
    ]
];

header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

