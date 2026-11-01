#!/usr/bin/php
<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');

function doLogin($username, $password)
{
    $login = new loginDB();
    return $login->validateLogin($username, $password);
}

function doRegister($name, $username, $email, $password)
{
    $register = new loginDB();

    return $register->registerUser(
        $name,
        $username,
        $email,
        $password
    );
}

function requestProcessor($request)
{
    echo "Received request type: "
        . ($request['type'] ?? 'unknown')
        . PHP_EOL;

    if (!isset($request['type'])) {
        return "ERROR: unsupported message type";
    }

    switch ($request['type']) {
        case "login":
            return doLogin(
                $request['username'],
                $request['password']
            );

        case "register":
            return doRegister(
                $request['name'],
                $request['username'],
                $request['email'],
                $request['password']
            );

        case "validate_session":
            $db = new loginDB();
            return $db->validateSession($request['token'] ?? '');

        case "logout":
            $db = new loginDB();
            return $db->logoutSession($request['token'] ?? '');
    }

    return array(
        'returnCode' => '0',
        'message' => 'Server received request and processed'
    );
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests('requestProcessor');

exit();
