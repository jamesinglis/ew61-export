<?php

/**
 * Main processing file for EasyWorship 6.1 Exporter
 *
 * @package    EasyWorship 6.1 Exporter
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author     James Inglis <hello@jamesinglis.no>
 * @version    0.2
 * @link       https://github.com/jamesinglis/ew61-export
 */

require 'config.php';
require 'functions.php';

$connection_songs = 'sqlite:' . $song_db_path;
$dbh = new PDO('sqlite:' . $song_db_path) or die("cannot open the database");
$dbh_lyrics = new PDO('sqlite:' . $song_words_db_path) or die("cannot open the database");
$query = "SELECT * FROM song";

if ($test_single_song_id && is_numeric($test_single_song_id)) {
    $query .= " WHERE rowid = " . $test_single_song_id;
}

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
        if ($file_export_type === 'propresenter6') {
            $songs[$id]['text'] = generate_prop6_file_contents($songs[$id]);
        } else {
            $songs[$id]['text'] = process_ew_lyrics($lyrics['words']);
        }
        save_text_file($songs[$id], $file_export_type);
    }
    if ($test_mode && $counter > 8) {
        break;
    }
    $counter++;
}