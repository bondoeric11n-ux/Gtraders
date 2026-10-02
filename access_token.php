<?php
// access_token.php
// Generates and caches Safaricom sandbox OAuth token.
// Set your sandbox consumer key / secret below.

date_default_timezone_set('Africa/Nairobi');

$MPESA_CONSUMER_KEY    = "RLFsAMTEQA7AWoyBYYyK3BVmTsvVyNGVRCyi6pq4uNKxNm4T";
$MPESA_CONSUMER_SECRET = "uWzHbPkZzzhWC9t5nAyAT80qE54Lz4ZiL2Ff2KevkMLSHHPdLylSPPjZTLQOKclX";
$MPESA_OAUTH_URL       = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
$MPESA_TOKEN_CACHE     = __DIR__ . "/mpesa_token.json"; // file to cache token

function getMpesaAccessToken() {
    global $MPESA_CONSUMER_KEY, $MPESA_CONSUMER_SECRET, $MPESA_OAUTH_URL, $MPESA_TOKEN_CACHE;

    // Try read cache
    if (file_exists($MPESA_TOKEN_CACHE)) {
        $cached = json_decode(@file_get_contents($MPESA_TOKEN_CACHE), true);
        if ($cached && !empty($cached['access_token']) && !empty($cached['expires_at'])) {
            if ($cached['expires_at'] > time() + 30) { // still valid
                return $cached['access_token'];
            }
        }
    }

    $credentials = base64_encode($MPESA_CONSUMER_KEY . ":" . $MPESA_CONSUMER_SECRET);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $MPESA_OAUTH_URL);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic {$credentials}"]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($curl);
    $err  = curl_error($curl);
    curl_close($curl);

    if ($err || !$resp) {
        error_log("Mpesa token fetch error: " . $err);
        return false;
    }

    $data = json_decode($resp, true);
    if (empty($data['access_token'])) {
        error_log("Mpesa token response invalid: " . $resp);
        return false;
    }

    // Cache with expiry (Safaricom tokens are valid 3600s)
    $cache = [
        'access_token' => $data['access_token'],
        'expires_at'   => time() + intval($data['expires_in'] ?? 3500)
    ];
    @file_put_contents($MPESA_TOKEN_CACHE, json_encode($cache));

    return $data['access_token'];
}

// If called directly, print token (helpful for debugging)
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $t = getMpesaAccessToken();
    if ($t) echo json_encode(['access_token' => $t]);
    else echo json_encode(['error' => 'Unable to fetch token']);
}
