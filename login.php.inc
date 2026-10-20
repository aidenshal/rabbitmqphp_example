<?php

class loginDB
{
    private $logindb;

    public function __construct()
    {
        $this->logindb = new mysqli(
            "127.0.0.1",
            "it490_app",
            "IT490_App_2026!",
            "login"
        );

        if ($this->logindb->connect_errno !== 0) {
            throw new RuntimeException(
                "Database connection failed: " .
                $this->logindb->connect_error
            );
        }

        $this->logindb->set_charset("utf8mb4");
        echo "correctly connected to database" . PHP_EOL;
    }

    public function validateLogin($username, $password)
    {
        if (!is_string($username) || !is_string($password)) {
            echo "username or password was missing" . PHP_EOL;
            return 0;
        }

        if ($username === "" || $password === "") {
            echo "username or password was empty" . PHP_EOL;
            return 0;
        }

        $statement = $this->logindb->prepare(
            "SELECT password FROM users WHERE screenname = ? LIMIT 1"
        );

        $statement->bind_param("s", $username);
        $statement->execute();

        $response = $statement->get_result();
        $row = $response->fetch_assoc();
        $statement->close();

        if ($row && password_verify($password, $row["password"])) {
            echo "passwords match for $username" . PHP_EOL;
            return 1;
        }

        echo "login failed for $username" . PHP_EOL;
        return 0;
    }
}
