<?php

$apiKey = "5959a150cd1169959431fba68ef22b94e43ee4096ea91b7c11d38092fa99ae5c";

// Coba beberapa endpoint Kapso untuk melihat webhook terdaftar
$endpoints = [
    "https://api.kapso.ai/v1/webhooks",
    "https://api.kapso.ai/platform/v1/webhooks",
    "https://app.kapso.ai/api/v1/whatsapp_configs",
    "https://api.kapso.ai/meta/whatsapp/v24.0/597907523413541",
];

foreach ($endpoints as $ep) {
    $ch = curl_init($ep);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-Key: " . $apiKey,
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Endpoint: $ep\nCode: $code\nResponse: " . substr($res, 0, 300) . "\n\n";
}
