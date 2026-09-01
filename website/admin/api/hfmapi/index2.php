<?php
header("Content-Type: application/json");

// ONLY DRE'S TOKEN IS USED
$dre_token = "59de3c4e-4870-4d8f-a69d-f5ff43ead532";

$mt5 = trim($_GET['mt5'] ?? '');
if (!preg_match('/^\d{6,12}$/', $mt5)) {
    die(json_encode(["result" => 0, "error" => "Invalid MT5"]));
}

$mt5_int = (int)$mt5;
$result = ["result" => 0, "mt5" => $mt5];
$found = false;

// SEARCH ONLY WITH DRE'S TOKEN
$ch = curl_init("https://api.hfaffiliates.com/api/clients/?page=1&per_page=10000");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $dre_token"],
    CURLOPT_USERAGENT      => "HFM-Fast/1.2",
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response) {
    $json = json_decode($response, true);
    if (is_array($json) && isset($json['data'])) {
        foreach ($json['data'] as $client) {
            if (isset($client['wallet']) && (int)$client['wallet'] === $mt5_int) {
                $result = [
                    "result"   => 1,
                    "mt5"      => $mt5,
                    "client"   => $client,
                    "_found_by"=> "jay"
                ];
                $found = true;
                break;
            }
        }
    }
}

// OUTPUT
if ($found) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "result" => 0,
        "mt5"    => $mt5,
        "error"  => "MT5 account not found under jay's affiliate clients"
    ], JSON_PRETTY_PRINT);
}