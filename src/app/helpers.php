<?php

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function generate_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function time_ago(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function format_ms(?int $ms): string {
    if ($ms === null) return '-';
    if ($ms < 1000) return $ms . ' ms';
    return round($ms / 1000, 1) . ' s';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
