<?php
declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php nvd_test.php CVE-YYYY-NUMBER\n";
    exit(1);
}

$cveId = strtoupper(trim($argv[1]));

if (!preg_match('/^CVE-\d{4}-\d{4,}$/', $cveId)) {
    echo "Invalid CVE format.\n";
    exit(1);
}

$url = 'https://services.nvd.nist.gov/rest/json/cves/2.0'
     . '?cveId='
     . urlencode($cveId);

$headers = [
    'Accept: application/json',
    'User-Agent: IT490-Vulnerability-Tracker/1.0'
];

$apiKey = getenv('NVD_API_KEY');

if ($apiKey !== false && $apiKey !== '') {
    $headers[] = 'apiKey: ' . $apiKey;
}

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$responseBody = curl_exec($curl);

if ($responseBody === false) {
    echo 'Request failed: ' . curl_error($curl) . PHP_EOL;
    curl_close($curl);
    exit(1);
}

$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($statusCode !== 200) {
    echo "NVD returned HTTP status $statusCode\n";
    echo $responseBody . PHP_EOL;
    exit(1);
}

$data = json_decode($responseBody, true);

if (!is_array($data)) {
    echo "NVD returned invalid JSON.\n";
    exit(1);
}

$vulnerabilities = $data['vulnerabilities'] ?? [];

if (count($vulnerabilities) === 0) {
    echo "No vulnerability found for $cveId\n";
    exit(0);
}

$cve = $vulnerabilities[0]['cve'] ?? [];

$description = 'No English description available.';

foreach ($cve['descriptions'] ?? [] as $item) {
    if (($item['lang'] ?? '') === 'en') {
        $description = $item['value'] ?? $description;
        break;
    }
}

$score = null;
$severity = 'UNKNOWN';
$vector = null;

$metricGroups = [
    'cvssMetricV40',
    'cvssMetricV31',
    'cvssMetricV30',
    'cvssMetricV2'
];

foreach ($metricGroups as $group) {
    if (!empty($cve['metrics'][$group][0]['cvssData'])) {
        $cvss = $cve['metrics'][$group][0]['cvssData'];

        $score = $cvss['baseScore'] ?? null;
        $severity = $cvss['baseSeverity']
            ?? ($cve['metrics'][$group][0]['baseSeverity'] ?? 'UNKNOWN');
        $vector = $cvss['vectorString'] ?? null;

        break;
    }
}

$normalizedCve = [
    'cve_id' => $cve['id'] ?? $cveId,
    'description' => $description,
    'severity' => $severity,
    'base_score' => $score,
    'vector' => $vector,
    'published_at' => $cve['published'] ?? null,
    'modified_at' => $cve['lastModified'] ?? null,
    'status' => $cve['vulnStatus'] ?? null,
    'source_url' => 'https://nvd.nist.gov/vuln/detail/' . $cveId
];

echo json_encode(
    $normalizedCve,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;