<?php
// includes/currency-helper.php

function getCurrencyConfig() {
    return [
        'code' => $_COOKIE['app_currency'] ?? 'MYR',
        'rate' => isset($_COOKIE['app_rate']) ? floatval($_COOKIE['app_rate']) : 1.0,
        'flag' => $_COOKIE['app_flag'] ?? '🇲🇾'
    ];
}

function formatCurrency($amount_myr) {
    $config = getCurrencyConfig();
    $converted_amount = $amount_myr * $config['rate'];
    
    // 格式化：货币代码 + 两位小数 (例如: USD 12.50)
    return $config['code'] . ' ' . number_format($converted_amount, 2);
}
?>