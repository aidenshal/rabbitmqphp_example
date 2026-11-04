<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$responseMessage = "";
$isSuccess = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $type = $_POST['type'] ?? '';

    if ($type === "register") {
        require_once(__DIR__ . '/../path.inc');
        require_once(__DIR__ . '/../get_host_info.inc');
        require_once(__DIR__ . '/../rabbitMQLib.inc');

        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        $request = array();
        $request['type'] = "register";
        $request['name'] = $name;
        $request['username'] = $username;
        $request['email'] = $email;
        $request['password'] = $password;
        $request['message'] = "Web form registration attempt";

        $response = $client->send_request($request);

        if ($response == 1) {
            header("Location: login.php");
            exit();
        } else {
            $responseMessage = "Registration Failed. Username may already exist.";
            $isSuccess = false;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>
    <h2>Register</h2>
<?php if ($responseMessage !== ''): ?>
    <p><?= htmlspecialchars($responseMessage, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
    <form action="register.php" method="POST">
        <input type="hidden" name="type" value="register">

        <label for="name">Name:</label><br>
        <input type="text" id="name" name="name" required><br><br>

        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Register</button>
    </form>

    <br>
    <a href="login.php">Already have an account? Log in</a>
</body>
</html>
