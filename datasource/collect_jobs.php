<?php

// Run from the terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
chdir($root);

try {
    $query = http_build_query([
        '$where' => "posting_type = 'External' AND career_level in ('Student', 'Entry-Level')",
        '$order' => 'posting_updated DESC, job_id ASC',
        '$limit' => 20
    ]);

    $url = 'https://data.cityofnewyork.us/resource/kpav-sd4t.json?' . $query;

    $context = stream_context_create([
        'http' => [
            'header' => "Accept: application/json\r\nUser-Agent: ApplyWeek/0.1\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0
        ]
    ]);

    $stream = @fopen($url, 'r', false, $context);

    if ($stream === false) {
        throw new RuntimeException('Could not connect to the jobs API.');
    }

    $body = stream_get_contents($stream);
    $meta = stream_get_meta_data($stream);
    fclose($stream);

    $status = $meta['wrapper_data'][0] ?? '';

    if ($body === false
        || $meta['timed_out']
        || !preg_match('/^HTTP\/\S+ 200\b/', $status)) {
        throw new RuntimeException('API request failed: ' . $status);
    }

    $jobs = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($jobs)
        || !array_is_list($jobs)
        || count($jobs) === 0) {
        throw new RuntimeException('The API did not return a nonempty jobs list.');
    }

    echo 'Fetched ' . count($jobs) . ' postings.' . PHP_EOL;

    foreach (array_slice($jobs, 0, 3) as $job) {
        echo ($job['job_id'] ?? '?') . ' | '
            . ($job['business_title'] ?? '?') . PHP_EOL;
    }

    // Preview tests the API without contacting RabbitMQ.
    if (in_array('--preview', $argv, true)) {
        exit(0);
    }

    require_once $root . '/path.inc';
    require_once $root . '/get_host_info.inc';
    require_once $root . '/rabbitMQLib.inc';

    $client = new rabbitMQClient(
        $root . '/testRabbitMQ.ini',
        'testServer'
    );

    $response = $client->send_request([
        'type' => 'import_jobs',
        'jobs' => $jobs
    ]);

    if (!is_array($response)
        || ($response['ok'] ?? false) !== true) {
        throw new RuntimeException('The worker did not confirm the import.');
    }

    echo 'Saved/updated ' . $response['processed']
        . ' postings.' . PHP_EOL;

} catch (Throwable $error) {
    fwrite(STDERR, 'Collector failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
