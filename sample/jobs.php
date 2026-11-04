<?php
header('Cache-Control: no-store, private');

$token = $_COOKIE['IT490_SESSION'] ?? '';

if (!is_string($token)
    || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    header('Location: login.php');
    exit;
}

$jobs = [];
$username = '';
$errorMessage = '';

try {
    $root = dirname(__DIR__);
    chdir($root);

    require_once $root . '/path.inc';
    require_once $root . '/get_host_info.inc';
    require_once $root . '/rabbitMQLib.inc';

    $client = new rabbitMQClient(
        $root . '/testRabbitMQ.ini',
        'testServer'
    );

    $response = $client->send_request([
        'type' => 'get_jobs',
        'token' => $token
    ]);

    if (is_array($response)
        && ($response['error'] ?? '') === 'unauthorized') {
        header('Location: login.php');
        exit;
    }

    if (!is_array($response)
        || ($response['ok'] ?? false) !== true
        || !is_array($response['jobs'] ?? null)
        || !is_string($response['username'] ?? null)) {
        throw new RuntimeException('Invalid jobs response.');
    }

    $jobs = $response['jobs'];
    $username = $response['username'];

} catch (Throwable $error) {
    error_log('ApplyWeek jobs page: ' . $error->getMessage());

    http_response_code(503);
    $errorMessage = 'Jobs are temporarily unavailable. Please try again later.';
}

function escapeHtml($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function salaryText($job)
{
    if (!is_numeric($job['salary_from'] ?? null)
        || !is_numeric($job['salary_to'] ?? null)) {
        return 'Not listed';
    }

    $frequency = $job['salary_frequency'] ?: 'frequency not listed';

    return '$' . number_format((float) $job['salary_from'], 2)
        . ' – $' . number_format((float) $job['salary_to'], 2)
        . ' (' . $frequency . ')';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Browse Jobs | ApplyWeek</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f3f3f3;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .error {
            color: #b00020;
        }
    </style>
</head>
<body>
    <h1>ApplyWeek — Browse Jobs</h1>

    <p><a href="dashboard.php">Back to dashboard</a></p>

    <?php if ($errorMessage !== ''): ?>

        <p role="alert" class="error">
            <?= escapeHtml($errorMessage) ?>
        </p>

    <?php else: ?>

        <p>Signed in as <?= escapeHtml($username) ?>.</p>
        <p>Showing <?= count($jobs) ?> collected postings, up to 50.</p>

        <?php if ($jobs === []): ?>

            <p>No jobs have been collected yet.</p>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Title</th>
                            <th>Agency</th>
                            <th>Career level</th>
                            <th>Salary (USD)</th>
                            <th>Collected (UTC)</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?= escapeHtml($job['job_id']) ?></td>
                                <td><?= escapeHtml($job['title']) ?></td>
                                <td><?= escapeHtml($job['agency']) ?></td>
                                <td><?= escapeHtml($job['career_level']) ?></td>
                                <td><?= escapeHtml(salaryText($job)) ?></td>
                                <td><?= escapeHtml($job['collected_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    <?php endif; ?>
</body>
</html>
