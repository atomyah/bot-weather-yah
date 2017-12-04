<?php

require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);


$userId = 'Uf9de62acca4142f05ce3db87a029b653';
$message = 'Hello Push API';

$response = $bot->pushMessage($userId, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));

if(!$response->isSucceeded()) {
  error_log('Failed', $response->getHTTPStatus() . ' ' . $response->getRawBody());
}


?>

