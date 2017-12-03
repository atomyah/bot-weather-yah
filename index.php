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
  if (($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    $location = $event->getText();
  } else if ($event instanceof \LINE\LINEBot\Event\MessageEvent\LocationMessage) {
    $jsonString = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?language=ja&latlng=' . $event->getLatitude() . ',' . $event->getLongitude());
    $json = json_decode($jsonString, TRUE);
    $addressComponentArray = $json['results'][0]['address_components'];
    
    foreach ($addressComponentArray as $addressComponent) {
      if(in_array('administrative_area_level_1', $addressComponent['types'])) {
        $prefName = $addressComponent['long_name'];   // $prefNameには神奈川県など県名が入るね。
        break;
      }
    }
    
    if($prefName == '東京都') {
      $location = '東京';
    } else if ($prefName == '大阪府') {
      $location = '大阪';
    } else {
      foreach ($addressComponentArray as $addressComponent) {
        if(in_array('locality', $addressComponent['types']) && !in_array('ward', $addressComponent['types'])) {
          $location = $addressComponent['long_name'];
          break;
        }           
      }
    }
    
  }
  

  
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
    if($event instanceof \LINE\LINEBot\Event\MessageEvent\LocationMessage) {
      $location = $prefName;
    }
    
    $suggestArray = array();
    
    foreach ($crawler->filter('channel ldWeather|source pref') as $pref) {
      if(strpos($pref->getAttribute('title'), $location) !== false) {     // strpos: 第2引数が第1引数に見つかる位置を返す。0番目もありえるので!=じゃダメ。
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
