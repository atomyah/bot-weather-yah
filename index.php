<?php
require_once __DIR__ .'/vendor/autoload.php';
require __DIR__ . '/functions.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch (\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log('ParseEventRequest failed. InvalidSignatureException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log('ParseEventRequest failed. UnknownEventTypeException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log('ParseEventRequest failed. UnknownMessageTypeException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log('ParseEventRequest failed. InvalidEventRequestException => '. var_export($e, TRUE));
}


foreach ($events as $event) {
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    error_log('not message event has come');
    continue;
  }
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    error_log('not text message has come');
    continue;
  }

  $location = $event->getText();
  
  $locationId;
  $client = new Goutte\Client();
  
  $crawler = $client->request('GET', 'http://weather.livedoor.com/forecast/rss/primary_area.xml');
  
  foreach ($crawler->filter('channel ldWeather|source pref city') as $city) {
    if($city->getAttribute('title') == $location || $city->getAttribute('title').'市' == $location) {
      $locationId = $city->getAttribute('id');
      break;
    }
  }
  
  if(empty($locationId)) {
    $suggestArray = array();
    
    foreach ($crawler->filter('channel ldWeather|source pref') as $pref) {
      if(strpos($pref->getAttribute('title'), $location) !== false) {     // strpos: 第一引数が第二引数に見つかる位置を返す。0番目もありえるので!=じゃダメ。
        foreach($pref->childNodes as $child) {
          if($child instanceof DOMElement && $child->nodeName == 'city') {
            array_push($suggestArray, $child->getAttribute('title'));
          }
        }
        break; 
      }
    }
    
    //ここからCity候補($suggestsArray)のボタンを作成
    if(count($suggestArray) >0) {
      $actionArray = array();
      
      foreach ($suggestArray as $city) {
        array_push($actionArray, new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder($city, $city));
      }
      $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder('見つかりませんでした', 
                 new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder('見つかりませんでした', 'もしかして?', NULL, $actionArray));
      $bot->replyMessage($event->getReplyToken(), $builder);
      
    } else {
      replyTextMessage($bot, $event->getReplyToken(), '入力された地名が見つかりませんでした。');
    }
    
    continue;
  }
  
  //やっとLocationIdを返す処理
  replyTextMessage($bot, $event->getReplyToken(), $location . 'の住所IDは' . $locationId . 'です。');
}


?>
