<?php
header("Content-Type: application/json");

// ======================= CONFIG =========================
$affiliate_tokens = [
    "Dre"      => "6c8ce185-3baa-4cad-a8cc-9a72a8ddf09e",
    "Jay"      => "59de3c4e-4870-4d8f-a69d-f5ff43ead532",
    "Phumlani" => "474aaed2-881a-489e-959b-e28036a2d0ca",
    "Ravs"     => "929f3163-c6cb-4b8d-94e1-c07099eb0a2d",
    "Asma"     => "1f258e51-cef2-4919-a984-898e0b86285",
    "Sizwe"    => "ea426893-b966-43fa-9f47-ee3ecf12991b",
    "AdditionalIB" => "5efae1cc-bac5-47f2-92ea-f8f86183424b",
];
$master_token = "ef83a937-261e-47f2-9fe5-03cbc6c90288";

$AFF_CACHE = __DIR__ . "/aff_cache.json";
$CAMP_CACHE = __DIR__ . "/camp_cache.json";

// =================== INPUT VALIDATION ===================
$mt5 = trim($_GET['mt5'] ?? '');
if (!preg_match('/^\d{6,12}$/', $mt5)) {
    die(json_encode(["result" => 0, "error" => "Invalid MT5"]));
}
$mt5_int = (int)$mt5;

// ========================================================
//                LOAD CACHE (if exists)
// ========================================================
$aff_cache  = file_exists($AFF_CACHE)  ? json_decode(file_get_contents($AFF_CACHE), true)  : [];
$camp_cache = file_exists($CAMP_CACHE) ? json_decode(file_get_contents($CAMP_CACHE), true) : [];

// =================================================================
//                     PHASE A: CHECK CACHE FIRST
// =================================================================

// ---- Check affiliate cache
if (isset($aff_cache[$mt5_int])) {
    echo json_encode([
        "result" => 1,
        "mt5"    => $mt5,
        "client" => $aff_cache[$mt5_int],
        "_found_by_affiliate_cache" => true
    ], JSON_PRETTY_PRINT);
    exit;
}

// ---- Check campaign cache
if (isset($camp_cache[$mt5_int])) {
    echo json_encode([
        "result" => 1,
        "mt5"    => $mt5,
        "registered_via_campaign" => $camp_cache[$mt5_int],
        "_found_by_campaign_cache" => true
    ], JSON_PRETTY_PRINT);
    exit;
}

// =================================================================
//       PHASE B: ONLY RUN API REQUESTS IF CACHE DIDN’T FIND IT
// =================================================================

$result = ["result" => 0, "mt5" => $mt5];
$found  = false;

// ========== Phase 1: Affiliate + Campaign List ==========
$mh = curl_multi_init();
$handles = [];

// --- Affiliate calls
foreach ($affiliate_tokens as $name => $token) {
    $ch = curl_init("https://api.hfaffiliates.com/api/clients/?page=1&per_page=200");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 7,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = ['type' => 'aff', 'name' => $name, 'ch' => $ch];
}

// --- Campaigns list
$ch = curl_init("https://api.hfaffiliates.com/api/campaigns/");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 7,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $master_token"],
]);
curl_multi_add_handle($mh, $ch);
$handles[] = ['type' => 'camplist', 'ch' => $ch];

do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running);

$campaign_acids = [];

foreach ($handles as $h) {
    $raw  = curl_multi_getcontent($h['ch']);
    $code = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($mh, $h['ch']);
    curl_close($h['ch']);

    if ($code !== 200 || !$raw) continue;
    $json = json_decode($raw, true);
    if (!isset($json['data'])) continue;

    // --- Affiliate
    if ($h['type'] === 'aff') {
        foreach ($json['data'] as $client) {

            // cache affiliate data
            if (isset($client['id'])) {
                $aff_cache[(int)$client['id']] = $client;
            }

            if ((int)$client['id'] === $mt5_int) {
                $found = true;
                $result = [
                    "result" => 1,
                    "mt5"    => $mt5,
                    "client" => $client,
                    "_found_by_affiliate" => $h['name']
                ];
                break 2;
            }
        }
    }

    // --- Campaign List acids
    if ($h['type'] === 'camplist') {
        foreach ($json['data'] as $camp) {
            if (preg_match('/acid=([a-z0-9]+)/i', $camp['main_link'] ?? $camp['hf_app_link'] ?? '', $m)) {
                $campaign_acids[$m[1]] = $camp['name'] ?? $m[1];
            }
        }
    }
}
curl_multi_close($mh);

// Update affiliate cache file
file_put_contents($AFF_CACHE, json_encode($aff_cache, JSON_PRETTY_PRINT));

// If found already in affiliate, return
if ($found) {
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// =================================================================
//                 PHASE 2: Campaign Accounts
// =================================================================

if ($campaign_acids) {

    $mh2 = curl_multi_init();
    $cam_handles = [];

    foreach ($campaign_acids as $acid => $name) {

        $url = "https://api.hfaffiliates.com/api/campaigns/accounts?" . http_build_query([
            "date_from"    => "2025-01-01",
            "date_to"      => date("Y-m-d"),
            "campaign_ids" => $acid
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $master_token"],
        ]);
        curl_multi_add_handle($mh2, $ch);

        $cam_handles[] = ['ch' => $ch, 'acid' => $acid, 'name' => $name];
    }

    do { curl_multi_exec($mh2, $running); curl_multi_select($mh2); } while ($running);

    foreach ($cam_handles as $h) {
        $raw = curl_multi_getcontent($h['ch']);
        $code = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh2, $h['ch']);
        curl_close($h['ch']);

        if ($code !== 200 || !$raw) continue;
        $json = json_decode($raw, true);
        if (!isset($json['data'])) continue;

        foreach ($json['data'] as $acc) {

            $acc_id = (int)$acc['account_id'];

            // Store in cache
            $camp_cache[$acc_id] = [
                "campaign_name" => $h['name'],
                "campaign_acid" => $h['acid'],
                "account_id"    => $acc_id,
                "country"       => $acc['country'] ?? "N/A",
                "email"         => $acc['email'] ?? "N/A"
            ];

            if ($acc_id === $mt5_int) {
                file_put_contents($CAMP_CACHE, json_encode($camp_cache, JSON_PRETTY_PRINT));

                echo json_encode([
                    "result" => 1,
                    "mt5"    => $mt5,
                    "registered_via_campaign" => $camp_cache[$mt5_int]
                ], JSON_PRETTY_PRINT);
                exit;
            }
        }
    }

    curl_multi_close($mh2);

    // save updated cache
    file_put_contents($CAMP_CACHE, json_encode($camp_cache, JSON_PRETTY_PRINT));
}

// =================================================================
//                    FINAL RESPONSE
// =================================================================

echo json_encode([
    "result" => 0,
    "error"  => "Not found in any affiliate or campaign"
], JSON_PRETTY_PRINT);

