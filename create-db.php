<?php

$db  = new SQLite3('news-articles.sql3');
$query = <<<EOD
  CREATE TABLE IF NOT EXISTS articles (
    aid INTEGER PRIMARY KEY,
    rid INTEGER KEY,
    title TEXT,
    url TEXT,
    created TEXT,
    teaser TEXT,
    image_url TEXT,
    save_hash TEXT,
    modified INT
    )
EOD;
$db->exec($query) or die('Create db failed');
