<?php
header('Cache-Control: no-store, private');

$token = $_COOKIE['IT490_SESSION'] ?? '';

if (!is_string($token)
    || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    header('Location: login.php');
    exit;
}

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
    'type' => 'validate_session',
    'token' => $token
]);

if (!is_array($response)
    || ($response['ok'] ?? false) !== true
    || !is_string($response['username'] ?? null)) {
    header('Location: login.php');
    exit;
}

$username = $response['username'];
$logoutCheck = hash_hmac('sha256', 'logout', $token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | ApplyWeek</title>
</head>
<body>
    <h1>Hello, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h1>

    <p>Welcome to ApplyWeek.</p>

    <p><a href="jobs.php">Browse jobs</a></p>

    <form action="logout.php" method="POST">
        <input
            type="hidden"
            name="csrf"
            value="<?= htmlspecialchars($logoutCheck, ENT_QUOTES, 'UTF-8') ?>"
        >
        <button type="submit">Log out</button>
    </form>
</body>
</html>
