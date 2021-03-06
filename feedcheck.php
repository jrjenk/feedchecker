<?php
/**
 * Created by PhpStorm.
 * User: jrjenk5
 * Date: 5/18/15
 */

// Set our TimeZone
date_default_timezone_set('America/Kentucky/Louisville');

// Whether we want to save the downloaded images.
$saveFiles = false;

// Whether to notify me no matter what.
$notify = false;

// Web Address of the JSON news feed
//$feed = "http://news.ca.uky.edu/json/100articles/all";
$feed = "http://news.ca.uky.edu/json/tarticles/all";

// Create a stream context.
$opts = array(
  'http'=>array(
    'method'=>"GET",
    'user_agent' => "jrj_feed_checker 1.1",
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

foreach ($jsondata["nodes"] as $node) {
  if (!empty($node["node"]["field_main_image_fid"])) {
    $nodeCount++;
    $fileData = null;
    $articleTitle = htmlspecialchars_decode($node["node"]["title"], ENT_QUOTES | ENT_HTML5);
    $remoteFileURL = $node["node"]["field_main_image_fid"];
    $targetParts = parse_url($remoteFileURL);
    $targetFile = pathinfo($targetParts["path"], PATHINFO_BASENAME);
    $remoteFileURL = $targetParts["scheme"] . "://" . $targetParts["host"] . str_replace(array(" "), array("%20"), $targetParts["path"]);
    $fileData = @file_get_contents($remoteFileURL, false, $context);

    if (empty($fileData)) {
      $nodeBadCount++;
      $messageLog[] = "In " . $articleTitle . " the file " . $targetFile . " contained no data.";
    }

    if ($saveFiles) {
      $targetFile = "/Users/jrjenk5/Development/FeedCheck/Output/" . $targetFile;
      // Create an output directory in the current working directory if needed
      if (!file_exists("/Users/jrjenk5/Development/FeedCheck/Output")) {
        mkdir("/Users/jrjenk5/Development/FeedCheck/Output", 0755);
      }

      // Open a File for writing, this will overwrite
      $targetFile = fopen($targetFile, "w");
      fwrite($targetFile, $fileData);
      fclose($targetFile);
    }
  }
}

if ($nodeBadCount > 0) {
  sendToProwl(implode("\n", $messageLog),2);
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
  sendToProwl("Cron is running (" . date('H:i') . ").", -2);
}


function sendToProwl($message = "", $priority = 2) {
  $postdata = http_build_query(
    array(
      "apikey" => "8d4ac096b6a5a85aa384dc41cbc179fb82b45a28",
      "priority" => $priority,
      "application" => "Feed Checker",
      "event" => "Broken Image",
      "description" => $message
    )
  );

  $opts = array('http' =>
    array(
      'method'  => 'POST',
      'content' => $postdata
    )
  );

  $context  = stream_context_create($opts);
  $result = @file_get_contents('https://api.prowlapp.com/publicapi/add', false, $context);
  writeLog("Sent Prowl Notification: " . $message);
}

function writeLog($message) {
  // Create Logs directory in the current working directory if needed
  if (!file_exists("/Users/jrjenk5/Development/FeedCheck/Logs")) {
    mkdir("/Users/jrjenk5/Development/FeedCheck/Logs", 0755);
  }
  $logFile = "/Users/jrjenk5/Development/FeedCheck/Logs/" . date('Y-m-d') . ".log";

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
      "title" => "Feed Checker",
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
