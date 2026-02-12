<?php
/**
 * Header Template
 * قالب رأس الصفحة
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = Auth::user();
if (!isset($settings)) {
    $settings = getStoreSettings();
}
$appName = $settings['store_name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? $appName ?> - <?= $appName ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "primary-dark": "#0e6ad0",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a2632",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "arabic": ["Cairo", "sans-serif"],
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px"},
                },
            },
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <?php if (isset($includeChartJS)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .font-num { font-family: 'Space Grotesk', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-background-light text-slate-800 min-h-screen">
