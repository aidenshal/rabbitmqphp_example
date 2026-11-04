<?php
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the Log out button on the dashboard.');
}

$token = $_COOKIE['IT490_SESSION'] ?? '';
$csrf = $_POST['csrf'] ?? '';

if (!is_string($token) ||
    !preg_match('/\A[a-f0-9]{64}\z/', $token) ||
    !is_string($csrf) ||
    !hash_equals(hash_hmac('sha256', 'logout', $token), $csrf)) {
    http_response_code(403);
    exit('Invalid logout request. Reload the dashboard and try again.');
}

require_once __DIR__ . '/../path.inc';
require_once __DIR__ . '/../get_host_info.inc';
require_once __DIR__ . '/../rabbitMQLib.inc';

$client = new rabbitMQClient(
    __DIR__ . '/../testRabbitMQ.ini',
    'testServer'
);

$response = $client->send_request([
    'type' => 'logout',
    'token' => $token
]);

if (!is_array($response) ||
    ($response['ok'] ?? false) !== true) {
    http_response_code(503);
    exit('Logout could not be confirmed. Go back and try again.');
}

setcookie('IT490_SESSION', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off'
]);

header('Location: login.php', true, 303);
exit();