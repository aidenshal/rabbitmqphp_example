#!/usr/bin/php
<?php

chdir(__DIR__);

require_once __DIR__ . '/path.inc';
require_once __DIR__ . '/get_host_info.inc';
require_once __DIR__ . '/rabbitMQLib.inc';
require_once __DIR__ . '/login.php.inc';

function doLogin($username, $password)
{
    $db = new loginDB();
    return $db->validateLogin($username, $password);
}

function doRegister($name, $username, $email, $password)
{
    $db = new loginDB();
    return $db->registerUser($name, $username, $email, $password);
}

function requestProcessor($request)
{
    if (!is_array($request)
        || !is_string($request['type'] ?? null)) {
        return [
            'ok' => false,
            'error' => 'invalid_request'
        ];
    }

    echo "Received request type: " . $request['type'] . PHP_EOL;

    switch ($request['type']) {
        case 'login':
            return doLogin(
                $request['username'] ?? '',
                $request['password'] ?? ''
            );

        case 'register':
            return doRegister(
                $request['name'] ?? '',
                $request['username'] ?? '',
                $request['email'] ?? '',
                $request['password'] ?? ''
            );

        case 'validate_session':
            $db = new loginDB();
            return $db->validateSession($request['token'] ?? '');

        case 'logout':
            $db = new loginDB();
            return $db->logoutSession($request['token'] ?? '');

        case 'import_jobs':
            $db = new loginDB();
            return $db->importJobs($request['jobs'] ?? []);

        case 'get_jobs':
            $db = new loginDB();
            return $db->getJobs($request['token'] ?? '');

        default:
            return [
                'ok' => false,
                'error' => 'unsupported_message_type'
            ];
    }
}

$server = new rabbitMQServer(
    __DIR__ . '/testRabbitMQ.ini',
    'testServer'
);

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
