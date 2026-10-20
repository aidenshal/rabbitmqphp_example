<?php
declare(strict_types=1);

set_include_path(
    '/opt/IT490' . PATH_SEPARATOR . get_include_path()
);

require_once '/opt/IT490/rabbitMQLib.inc';

$error = '';
$success = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request = $_POST;
    $requestType = $request['type'] ?? '';

    switch ($requestType) {
        case 'login':
            $username = trim($request['username'] ?? '');
            $password = $request['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Username and password are required.';
                break;
            }

            if (
                !preg_match(
                    '/^[A-Za-z0-9_]{3,32}$/',
                    $username
                )
            ) {
                $error = 'Please enter a valid username.';
                break;
            }

            try {
                $client = new rabbitMQClient(
                    '/opt/IT490/rabbitmq.ini',
                    'IT490AuthServer'
                );

                $rabbitRequest = [
                    'type' => 'login',
                    'username' => $username,
                    'password' => $password,
                    'message' => 'IT490 web login'
                ];

                $response = $client->send_request(
                    $rabbitRequest
                );

                if (
                    $response === 1 ||
                    $response === true ||
                    $response === '1'
                ) {
                    $success = 'Login successful!';
                    $username = '';
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Throwable $exception) {
                error_log(
                    'IT490 login error: ' .
                    $exception->getMessage()
                );

                $error = 'The login service is unavailable.';
            }

            break;

        default:
            $error = 'Unsupported request type.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Login</title>
</head>
<body>
    <h2>Login</h2>

    <?php if ($error !== ''): ?>
        <p style="color: red;">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <p style="color: green;">
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input
            type="hidden"
            name="type"
            value="login"
        >

        <label for="username">Username:</label><br>

        <input
            id="username"
            type="text"
            name="username"
            value="<?= htmlspecialchars(
                $username,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            minlength="3"
            maxlength="32"
            autocomplete="username"
            required
        ><br><br>

        <label for="password">Password:</label><br>

        <input
            id="password"
            type="password"
            name="password"
            maxlength="72"
            autocomplete="current-password"
            required
        ><br><br>

        <input type="submit" value="Log In">
    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>
</body>
</html>
