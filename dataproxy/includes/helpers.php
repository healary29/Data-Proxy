<?php
// =========================================================
// helpers.php — small shared PHP helper functions
// =========================================================

if (!function_exists('formatMoney')) {
    function formatMoney($amount): string {
        return 'KSh ' . number_format((float) $amount, 0);
    }
}
