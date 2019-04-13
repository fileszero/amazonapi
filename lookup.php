<?php
require_once('vendor/autoload.php');
require_once('conf.php'); // ACCESS_KEY, SECRET_KEY, ASSOCIATE_TAGを定義

const RETRY_COUNT = 5; // 試行回数
const RETRY_SLEEP_SEC = 10; // 試行時の待ち時間(秒)

const RESULT_ERROR_JSON = '{ "result": false }';

use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;
use ApaiIO\ApaiIO;

if(empty($_GET['item'])){
  $itemId =$argv[1];
} else {
  $itemId = $_GET['item'];
}

if ($itemId) {
  $item = getItem($itemId);

  if ($item) {
    $ret = toJson($item);
  } else {
    $ret = RESULT_ERROR_JSON;
  }
} else {
  $ret = RESULT_ERROR_JSON;
}

header('Content-Type: application/json');
echo $ret;

/**
 * Amazonに問い合わせてアイテム情報を取得します。
 *
 * @param $itemId ASIN
 * @return object
 */
function getItem($itemId) {

  $conf = new GenericConfiguration();
  $client = new \GuzzleHttp\Client();
  $request = new \ApaiIO\Request\GuzzleRequest($client);

  $conf
    ->setCountry('co.jp')
    ->setAccessKey(ACCESS_KEY)
    ->setSecretKey(SECRET_KEY)
    ->setAssociateTag(ASSOCIATE_TAG)
    ->setRequest($request);

  $apaiIO = new ApaiIO($conf);

  $lookup = new Lookup();
  $lookup->setItemId($itemId);
  $lookup->setResponseGroup(array('Images', 'Small'));

  $item = NULL;

  // Product Advertising APIが503を返すことがあるので一定回数リトライする
  for ($i = 0 ; $i < RETRY_COUNT; $i++) {

    try {
      $res = $apaiIO->runOperation($lookup);
      $results = simplexml_load_string($res);

      if ($results->Items->Request->IsValid) {
        $item = $results->Items->Item[0];
      }

      break; // APIからレスポンスが返ってきたら成否を問わず処理打ち切り

    } catch (Exception $e) {
      var_dump($e);
      sleep(RETRY_SLEEP_SEC); // リトライの前に一定時間待つ
    }
  }

  return $item;
}

/**
 * アイテムから必要な情報を抜き出してJSON形式で返します。
 *
 * @param $item
 * @return string
 */
function toJson($item) {

  $array = array(
    "result" => 'true',
    "asin"=> (string) $item->ASIN,
    "title"=> (string) $item->ItemAttributes->Title,
    "author" => (string) $item->ItemAttributes->Author,
    "manufacturer" => (string) $item->ItemAttributes->Manufacturer,
    "item_url"=> (string) $item_url = $item->DetailPageURL,
    "image_url"=> (string) $item->MediumImage->URL,
    "associate_id"=> (string) ASSOCIATE_TAG,
  );

  return json_encode($array);
}