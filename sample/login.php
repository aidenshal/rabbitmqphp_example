<?php

$responseMessage = "";
$isSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $type = $_POST['type'] ?? '';

    if ($type === "login") {
        $projectRoot = dirname(__DIR__);
        chdir($projectRoot);

        require_once $projectRoot . '/path.inc';
        require_once $projectRoot . '/get_host_info.inc';
        require_once $projectRoot . '/rabbitMQLib.inc';

        $client = new rabbitMQClient(
            $projectRoot . '/testRabbitMQ.ini',
            'testServer'
        );

        $request = [
            'type' => 'login',
            'username' => $username,
            'password' => $password,
            'message' => 'Web form login attempt'
        ];

        $response = $client->send_request($request);

        if (is_array($response) &&
            ($response['ok'] ?? false) === true &&
            is_string($response['token'] ?? null)) {
            setcookie('IT490_SESSION', $response['token'], [
                'expires' => time() + 1800,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off'
            ]);

            header("Location: dashboard.php");
            exit;
        }

        $responseMessage = "Login failed. Invalid username or password.";
        $isSuccess = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | ApplyWeek</title>
</head>
<body>
    <h2>Login</h2>

    <?php if ($responseMessage !== ''): ?>
        <p role="alert" style="color: #b00020;">
            <?= htmlspecialchars($responseMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="hidden" name="type" value="login">

        <label for="username">Username:</label><br>
        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
        ><br><br>

        <label for="password">Password:</label><br>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        ><br><br>

        <button type="submit">Log In</button>

        <p>
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>
    </form>
</body>
</html>
