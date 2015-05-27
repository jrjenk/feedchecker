<?php
/**
 * Created by PhpStorm.
 * User: jrjenk5
 * Date: 5/27/15
 */

// Set our TimeZone
date_default_timezone_set('America/Kentucky/Louisville');

// Our SQLite3 Database
$db  = new SQLite3('news-articles.sql3');


// Whether to notify me no matter what.
$notify = false;

// Web Address of the JSON news feed
//$feed = "http://news.ca.uky.edu/json/100articles/all";
$feed = "http://news.ca.uky.edu/json/tarticles/all";

// Create a stream context.
$opts = array(
  'http'=>array(
    'method'=>"GET",
    'user_agent' => "jrj_feed_recorder 1.1",
    'header'=>"Accept-language: en\r\n",
    'timeout' => 60
  )
);
$context = stream_context_create($opts);

// Fetch the JSON feed, and convert it back into a JSON object.
$file = file_get_contents($feed, false, $context);
$jsondata = json_decode($file, true);
$nodeCount = 0;
$nodeBadCount = 0;
$messageLog = [];
$insertQuery = '';

foreach ($jsondata["nodes"] as $node) {
    $nodeCount++;
    $fileData = null;
    $articleID = $node["node"]["id"];
    $articleTitle = htmlspecialchars_decode($node["node"]["title"], ENT_QUOTES | ENT_HTML5);
    $articleURL = $node["node"]["url"];
    $articleCreated = $node["node"]["created"];
    $articleTeaser = $node["node"]["field_teaser_value"];
    $remoteFileURL = $node["node"]["field_main_image_fid"];
    $hashable = $articleTitle . $articleURL . $articleTeaser . $remoteFileURL;
    $articleHash = hash('md5', $hashable);

  // if articleID in DB then
  //  if new hash not the same as old hash
  //    update fields and set modified = 1
  //  else
  //    do nothing
  // else
  //  save new article to database.



    $insertStmt = $db->prepare('INSERT INTO articles (rid, title, url, created, teaser, image_url, save_hash) VALUES (:rid, :title, :url, :created, :teaser, :image_url, :save_hash)');
    $insertStmt->bindValue(':rid', $articleID, SQLITE3_INTEGER);
    $insertStmt->bindValue(':title', $articleTitle, SQLITE3_TEXT);
    $insertStmt->bindValue(':url', $articleURL, SQLITE3_TEXT);
    $insertStmt->bindValue(':created', $articleCreated, SQLITE3_TEXT);
    $insertStmt->bindValue(':teaser', $articleTeaser, SQLITE3_TEXT);
    $insertStmt->bindValue(':save_hash', $articleHash, SQLITE3_TEXT);

    if (empty($remoteFileURL)) {
      $insertStmt->bindValue(':image_url', null, SQLITE3_TEXT);
    } else {
      $targetParts = parse_url($remoteFileURL);
      $targetFile = pathinfo($targetParts["path"], PATHINFO_BASENAME);
      $remoteFileURL = $targetParts["scheme"] . "://" . $targetParts["host"] . str_replace(array(" "), array("%20"), $targetParts["path"]);
      $insertStmt->bindValue(':image_url', $remoteFileURL, SQLITE3_TEXT);
    }




    $result = $insertStmt->execute();
    $insertStmt->close();
}

if ($nodeBadCount > 0) {

  sendToPushover(implode("\n", $messageLog),1);
  $messages = "Processed $nodeCount nodes with $nodeCount errors.\n";
  foreach ($messageLog as $message) {
    $messages = $messages . " - " . $message . "\n";
  }
  writeLog($messages);
} else {
  writeLog("Processed $nodeCount nodes with no errors.");
  $currentHour = date("H");
  $currentMinute = date("i");
  if ((strcmp($currentMinute, "00") === 0) && in_array($currentHour, array("08","12","19"))) {
    sendToPushover("Processed $nodeCount nodes with no errors.", -1);
  }
}

if ($notify) {
  sendToPushover("Cron is running (" . date('H:i') . ").", -1);
}


function writeLog($message) {
  // Create Logs directory in the current working directory if needed
  if (!file_exists("/Users/jrjenk5/Development/FeedCheck/RecorderLogs")) {
    mkdir("/Users/jrjenk5/Development/FeedCheck/RecorderLogs", 0755);
  }
  $logFile = "/Users/jrjenk5/Development/FeedCheck/RecorderLogs/" . date('Y-m-d') . ".log";

  if (file_exists($logFile)) {
    $logHandle = fopen($logFile, "a");
  } else {
    $logHandle = fopen($logFile, "a");
    fwrite($logHandle, "Date,Time,Message\n");
  }

  $logMessage = date('Y-m-d') . ", " . date('H:i:s') . ", " . $message . "\n";

  fwrite($logHandle, $logMessage);
  fclose($logHandle);
}

function sendToPushover($message = "", $priority = 0) {
  $postdata = http_build_query(
    array(
      "token" => "aNwkRdGACJ9Aho39XTMfrQF436oJTN",
      "user" => "gGo7Cspio5JdEfHi81V78qJUDcwR5r",
      "title" => "Feed Recorder",
      "priority" => $priority,
      "sound" => "bugle",
      "message" => $message
    )
  );

  $opts = array('http' =>
    array(
      'method'  => 'POST',
      'content' => $postdata
    )
  );

  $context  = stream_context_create($opts);
  $result = @file_get_contents('https://api.pushover.net/1/messages.json', false, $context);
  $objResult = json_decode($result, true);
  if ($objResult["status"] == 1) {
    writeLog("Sent Pushover Notification (" . $objResult["request"] . "): " . $message);
  } else {
      writeLog("Pushover Notification (" . $objResult["request"] . ") Failed with errors : " . $objResult["errors"] . "\nPayload:" . $message);
  }

}
