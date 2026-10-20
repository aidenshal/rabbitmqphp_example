<?php
include 'db_connection.php';

if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $name === '' ||
        $username === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $error = "All fields are required.";
    } elseif (strlen($name) > 100) {
        $error = "Name is too long.";
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $error = "Username must be 3–32 letters, numbers, or underscores.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email.";
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = "Password must be between 8 and 72 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = "Password must contain at least one symbol.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Check whether the email or username already exists.
        $check = $conn->prepare(
            "SELECT id FROM users
             WHERE email = ? OR screenname = ?
             LIMIT 1"
        );

        $check->bind_param("ss", $email, $username);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email or username already exists.";
        } else {
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insert = $conn->prepare(
                "INSERT INTO users
                    (name, screenname, email, password)
                 VALUES (?, ?, ?, ?)"
            );

            $insert->bind_param(
                "ssss",
                $name,
                $username,
                $email,
                $passwordHash
            );

            if ($insert->execute()) {
                $success = "Registration successful!";

                // Clear the form after successful registration.
                $name = '';
                $username = '';
                $email = '';
            } else {
                $error = "Registration failed. Please try again.";
            }

            $insert->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Form</title>
</head>
<body>
    <h2>Register Form</h2>

    <?php if (isset($error)): ?>
        <p style="color:red;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <p style="color:green;">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label>Name:</label><br>
        <input
            type="text"
            name="name"
            maxlength="100"
            value="<?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        ><br><br>

        <label>Username:</label><br>
        <input
            type="text"
            name="username"
            minlength="3"
            maxlength="32"
            pattern="[A-Za-z0-9_]{3,32}"
            value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        ><br><br>

        <label>Email:</label><br>
        <input
            type="email"
            name="email"
            maxlength="254"
            value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        ><br><br>

        <label>Password:</label><br>
        <input
            type="password"
            name="password"
            minlength="8"
            maxlength="72"
            title="At least 8 characters with uppercase, lowercase, number, and symbol."
            required
        ><br>
        <small>
            At least 8 characters with an uppercase letter,
            lowercase letter, number, and symbol.
        </small><br><br>

        <label>Confirm Password:</label><br>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            maxlength="72"
            required
        ><br><br>

        <input type="submit" name="register" value="Register">
    </form>
</body>
</html>
