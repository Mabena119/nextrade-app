<?php
header("Content-Type: application/json");

// MASTER TOKEN ONLY
$master_token = "ef83a937-261e-47f2-9fe5-03cbc6c90288";

$mt5 = trim($_GET['mt5'] ?? '');
if (!preg_match('/^\d{6,12}$/', $mt5)) {
    die(json_encode(["result" => 0, "error" => "Invalid MT5"]));
}

$mt5_int = (int)$mt5;
$result  = ["result" => 0, "mt5" => $mt5];
$found   = false;

// PHASE 1: Search all affiliate clients in parallel (page=1&per_page=200 → covers 99% of cases fast)
$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < 8; $i++) { // usually 6–8 affiliates max
    $ch = curl_init("https://api.hfaffiliates.com/api/clients/?page=1&per_page=200");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $master_token"],
        CURLOPT_USERAGENT => "HFM-MasterLookup/1.0",
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = ['type' => 'aff', 'ch' => $ch];
}

// PHASE 2: Also grab campaign list (for later campaign lookup if needed)
$ch = curl_init("https://api.hfaffiliates.com/api/campaigns/");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $master_token"],
]);
curl_multi_add_handle($mh, $ch);
$handles[] = ['type' => 'camplist', 'ch' => $ch];

// Execute all requests in parallel
do { curl_multi_exec($mh, $running); } while ($running);

$campaign_acids = [];

foreach ($handles as $h) {
    $raw  = curl_multi_getcontent($h['ch']);
    $code = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($mh, $h['ch']);
    curl_close($h['ch']);

    if ($code !== 200 || !$raw) continue;

    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['data'])) continue;

    if ($h['type'] === 'aff') {
        foreach ($json['data'] as $client) {
            if (isset($client['wallet']) && (int)$client['wallet'] === $mt5_int) {
                $result = [
                    "result" => 1,
                    "mt5"    => $mt5,
                    "found_in" => "affiliate_clients",
                    "client"   => $client
                ];
                $found = true;
                break 2;
            }
        }
    }

    if ($h['type'] === 'camplist') {
        foreach ($json['data'] as $camp) {
            if (preg_match('/acid=([a-z0-9]+)/i', $camp['main_link'] ?? $camp['hf_app_link'] ?? '', $m)) {
                $campaign_acids[$m[1]] = $camp['name'] ?? $m[1];
            }
        }
    }
}
curl_multi_close($mh);

// If already found in affiliate clients → instant return (fastest)
if ($found) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// PHASE 3: If not found in affiliates → search inside campaigns (master token can do this)
if (!$found && $campaign_acids) {
    $mh2 = curl_multi_init();
    $cam_handles = [];

    foreach ($campaign_acids as $acid => $name) {
        $url = "https://api.hfaffiliates.com/api/campaigns/accounts?" . http_build_query([
            'date_from' => '2024-01-01',
            'date_to'   => date('Y-m-d'),
            'campaign_ids' => $acid
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $master_token"],
        ]);
        curl_multi_add_handle($mh2, $ch);
        $cam_handles[] = ['ch' => $ch, 'acid' => $acid, 'name' => $name];
    }

    do { curl_multi_exec($mh2, $running); } while ($running);

    foreach ($cam_handles as $h) {
        $raw  = curl_multi_getcontent($h['ch']);
        $code = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh2, $h['ch']);
        curl_close($h['ch']);

        if ($code !== 200 || !$raw) continue;
        $json = json_decode($raw, true);
        if (empty($json['data'])) continue;

        foreach ($json['data'] as $acc) {
            if ((int)$acc['account_id'] === $mt5_int) {
                $result = [
                    "result" => 1,
                    "mt5"    => $mt5,
                    "found_in" => "campaign_accounts",
                    "campaign_name" => $h['name'],
                    "campaign_acid" => $h['acid'],
                    "account" => $acc
                ];
                $found = true;
                break 2;
            }
        }
    }
    curl_multi_close($mh2);
}

// FINAL OUTPUT
echo $found
    ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    : json_encode(["result" => 0, "mt5" => $mt5, "error" => "Not found anywhere"], JSON_PRETTY_PRINT);