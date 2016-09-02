<?php

require 'config.php';
require 'functions.php';

$connection_songs = 'sqlite:' . $song_db_path;
$dbh = new PDO('sqlite:' . $song_db_path) or die("cannot open the database");
$dbh_lyrics = new PDO('sqlite:' . $song_words_db_path) or die("cannot open the database");
$query = "SELECT * FROM song";

$songs = [];
foreach ($dbh->query($query) as $song) {
    $songs[$song['rowid']] = [
        'id' => $song['rowid'],
        'title' => process_ew_title($song['title'])
    ];
}

$counter = 0;

foreach ($songs as $id => $song) {
    $query2 = "SELECT words FROM word WHERE song_id = $id";
    foreach ($dbh_lyrics->query($query2) as $lyrics) {
        $songs[$id]['text'] = process_ew_lyrics($lyrics['words']);
        save_text_file($songs[$id]);
    }
    if ($test_mode && $counter > 8) {
        break;
    }
    $counter++;
}