<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$typography = file_get_contents($root . '/app/Views/public/_layout_typography.php');
$header = file_get_contents($root . '/app/Views/public/_layout_start.php');
$footer = file_get_contents($root . '/app/Views/public/_layout_end.php');

$checks = [
    'home hero excluded from shared internal hero selector' => !str_contains($typography, ':is(.home-hero'),
    'internal heroes share rhythm' => str_contains($typography, '.faq-hero,.wifi-hero){padding:60px 0 68px'),
    'repeated labels share typography' => str_contains($typography, '.faq-eyebrow,.wifi-eyebrow){font-family:var(--font-body)'),
    'mobile internal hero rhythm exists' => str_contains($typography, 'padding:44px 0 48px'),
    'header uses complete technical sheets label' => substr_count($header, '>Schede tecniche</a>') === 2,
    'footer uses complete technical sheets label' => substr_count($footer, '>Schede tecniche</a>') === 1,
    'footer uses consistent price list label' => substr_count($footer, '>Listino prezzi</a>') === 1,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Visual consistency smoke failed: {$label}\n");
        exit(1);
    }
}

echo "Visual consistency smoke passed.\n";
