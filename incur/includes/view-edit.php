<?php
// Shared helpers for read-only / edit view blocks (data-view-edit pattern)

function hds_ve_display($value, string $default = '—'): string {
    $trimmed = trim((string)($value ?? ''));
    if ($trimmed === '') {
        return $default;
    }
    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}