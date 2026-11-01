#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');

function doLogin($username,$password)
{
  $login = new loginDB();
  return $login->validateLogin($username,$password);
}

function doRegister($name,$username,$email,$password)
{
  $register = new loginDB();
  return $register->registerUser($name,$username,$email,$password);
}

//function doValidate($sessionId)
//{
//  $login = new loginDB();
//  return $login->validateSession($sessionId);
//}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "import_jobs":
    $db = new loginDB();
    	return $db->importJobs($request['jobs'] ?? []);
    case "login":
      return doLogin($request['username'],$request['password']);
    case "register":
      return doRegister($request['name'],$request['username'],$request['email'],$request['password']);
    case "validate_session":
    $db = new loginDB();
    return $db->validateSession($request['token'] ?? '');
    case "logout":
    $db = new loginDB();
    return $db->logoutSession($request['token'] ?? '');
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

