<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$responseMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $type = $_POST['type'] ?? '';

    if ($type === 'register') {
        $projectRoot = dirname(__DIR__);
        chdir($projectRoot);

        require_once $projectRoot . '/path.inc';
        require_once $projectRoot . '/get_host_info.inc';
        require_once $projectRoot . '/rabbitMQLib.inc';

        $client = new rabbitMQClient(
            $projectRoot . '/testRabbitMQ.ini',
            'testServer'
        );

        $response = $client->send_request([
            'type' => 'register',
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'message' => 'Web form registration attempt'
        ]);

        if (is_array($response)
            && ($response['ok'] ?? false) === true) {
            header('Location: login.php');
            exit;
        }

        $responseMessage =
            is_array($response)
            && is_string($response['message'] ?? null)
                ? $response['message']
                : 'Registration failed. Please try again.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | ApplyWeek</title>
</head>
<body>
    <h2>Register</h2>

    <?php if ($responseMessage !== ''): ?>
        <p role="alert">
            <?= htmlspecialchars($responseMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <input type="hidden" name="type" value="register">

        <label for="name">Name:</label><br>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            required
        ><br><br>

        <label for="username">Username:</label><br>
        <input
            type="text"
            id="username"
            name="username"
            maxlength="32"
            autocomplete="username"
            required
        ><br><br>

        <label for="email">Email:</label><br>
        <input
            type="text"
            id="email"
            name="email"
            inputmode="email"
            maxlength="254"
            autocomplete="email"
            required
        ><br><br>

        <label for="password">Password:</label><br>
        <input
            type="password"
            id="password"
            name="password"
            minlength="5"
            maxlength="72"
            autocomplete="new-password"
            required
        ><br>
        <small>At least 5 characters.</small><br><br>

        <button type="submit">Register</button>
    </form>

    <p>
        <a href="login.php">Already have an account? Log in</a>
    </p>
</body>
</html>
