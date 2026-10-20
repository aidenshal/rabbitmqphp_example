<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(bool $ok, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'This endpoint only accepts registration requests.', 405);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    respond(false, 'The submitted form is too large.', 413);
}

function post_text(string $name): string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}

// Quietly absorb basic bot submissions. Real rate limiting still belongs in the backend worker.
if (post_text('website') !== '') {
    respond(true, 'Account request received.');
}

$username = strtolower(trim(post_text('username')));
$email = strtolower(trim(post_text('email')));
$password = post_text('password');
$confirmation = post_text('confirm_password');

if (!preg_match('/\A[a-z0-9_]{3,32}\z/', $username)) {
    respond(false, 'Use 3–32 letters, numbers, or underscores for your username.', 422);
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Enter a valid email address.', 422);
}
if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
    respond(false, 'Use a password between 12 and 72 bytes.', 422);
}
if (!hash_equals($password, $confirmation)) {
    respond(false, 'Your passwords do not match.', 422);
}
if (post_text('agreement') !== 'accepted') {
    respond(false, 'Please acknowledge the account notice.', 422);
}

/*
 * This sample expects this directory layout:
 *
 * rabbitmqphp_example/
 * ├── rabbitMQLib.inc
 * ├── testRabbitMQ.ini
 * └── sample/
 *     ├── register.php
 *     └── register_api.php
 */
$projectRoot = dirname(__DIR__);
$rabbitLibrary = $projectRoot . '/rabbitMQLib.inc';
$rabbitConfig = $projectRoot . '/testRabbitMQ.ini';

if (!is_readable($rabbitLibrary) || !is_readable($rabbitConfig)) {
    respond(false, 'Registration is not connected to the message service yet.', 503);
}

try {
    require_once $rabbitLibrary;
    if (!class_exists('rabbitMQClient')) {
        throw new RuntimeException('RabbitMQ client class was not loaded.');
    }

    $client = new rabbitMQClient($rabbitConfig, 'testServer');
    $backendResponse = $client->send_request([
        'type' => 'register',
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $confirmation,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);

    // Accept the most common response shapes while your group settles on one contract.
    $success = $backendResponse === true
        || (is_array($backendResponse) && ($backendResponse['ok'] ?? false) === true);

    if ($success) {
        respond(true, 'Account created. You can now log in.');
    }

    $message = is_array($backendResponse) && is_string($backendResponse['message'] ?? null)
        ? $backendResponse['message']
        : 'The backend could not create that account.';
    respond(false, $message, 409);
} catch (Throwable $error) {
    // Keep broker addresses, credentials, stack traces, and passwords out of browser responses.
    error_log('Registration request failed: ' . get_class($error));
    respond(false, 'Registration is temporarily unavailable. Please try again shortly.', 503);
}
