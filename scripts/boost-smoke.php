<?php

declare(strict_types=1);

use Laravel\Boost\Install\MarkdownFormatter;

require __DIR__.'/vendor/autoload.php';
$source = file_get_contents(__DIR__.'/vendor/geolize-studio/laravel-chile/resources/boost/skills/chile-development/SKILL.md');
$installed = file_get_contents(__DIR__.'/.agents/skills/chile-development/SKILL.md');
$guidelines = file_get_contents(__DIR__.'/AGENTS.md');
if ($installed !== MarkdownFormatter::format(trim($source))."\n"
    || substr_count($guidelines, '<laravel-boost-guidelines>') !== 1
    || ! str_contains($guidelines, 'Consumer-owned guidance.')
    || ! str_contains($guidelines, '=== geolize-studio/laravel-chile/core rules ===')
    || ! str_contains($guidelines, 'RoundingMode')) {
    throw new RuntimeException('Boost did not preserve consumer guidance and install the expected package resources.');
}

$preferences = json_decode(file_get_contents(__DIR__.'/boost.json'), true, flags: JSON_THROW_ON_ERROR);
if ($preferences['agents'] !== ['codex']
    || ! in_array('geolize-studio/laravel-chile', $preferences['packages'], true)
    || is_dir(__DIR__.'/.codex') || is_dir(__DIR__.'/.claude') || is_file(__DIR__.'/.mcp.json')) {
    throw new RuntimeException('Boost changed unexpected agent preferences or destinations.');
}
echo "Boost resources verified.\n";
