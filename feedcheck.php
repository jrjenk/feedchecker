<?php
/**
 * Created by PhpStorm.
 * User: jrjenk5
 * Date: 5/18/15
 */

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
    'header'=>"Accept-language: en\r\n",
    'timeout' => 60
  )
);
$context = stream_context_create($opts);

// Fetch the JSON feed, and convert it back into a JSON object.
$file = file_get_contents($feed, false, $context);
$jsondata = json_decode($file, true);

foreach ($jsondata["nodes"] as $node) {
  if (!empty($node["node"]["field_main_image_fid"])) {
    $fileData = null;
    $articleTitle = htmlspecialchars_decode($node["node"]["title"], ENT_QUOTES | ENT_HTML5);
    $remoteFileURL = $node["node"]["field_main_image_fid"];
    $targetParts = parse_url($remoteFileURL);
    $targetFile = pathinfo($targetParts["path"], PATHINFO_BASENAME);
    $remoteFileURL = $targetParts["scheme"] . "://" . $targetParts["host"] . str_replace(array(" "), array("%20"), $targetParts["path"]);
    $fileData = @file_get_contents($remoteFileURL, false, $context);

    if (empty($fileData)) {
      sendToProwl("In " . $articleTitle . " the file " . $targetFile . " contained no data.", 2);
    }

    if ($saveFiles) {
      $targetFile = "/Users/jrjenk5/Development/FeedCheck/Output/" . $targetFile;
      print "Processing: " . $articleTitle . "\n";
      // Create an output directory in the current working directory if needed
      if (!file_exists("./Output")) {
        mkdir("/Users/jrjenk5/Development/FeedCheck/Output", 0755);
      }

      // Open a File for writing, this will overwrite
      $targetFile = fopen($targetFile, "w");
      fwrite($targetFile, $fileData);
      fclose($targetFile);
    }
  }
}


if ($notify) {
  date_default_timezone_set('America/Kentucky/Louisville');
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
}
