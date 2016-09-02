<?php

$test_mode = false;

$song_db_path = __DIR__ . '/databases/Songs.db';
$song_words_db_path = __DIR__ . '/databases/SongWords.db';
$output_directory = __DIR__ . '/output/';

function process_ew_title($title)
{
    $title = trim(ucwords($title));
    $title = preg_replace("/\s{2,}/", ' ', $title);
    $lc_words = ['and', 'of', 'the'];
    foreach ($lc_words as $lc_word) {
        $title = str_replace(ucwords($lc_word), $lc_word, $title);
    }
    $title = ucfirst($title);
    return $title;
}

function process_ew_lyrics($text)
{
    $text_lines = explode(PHP_EOL, $text);
    foreach ($text_lines as &$text_line) {
        $text_line = process_ew_lyrics_line($text_line);
        $text_line = process_ew_lyrics_line_custom($text_line);
        $text_line = process_ew_lyrics_line_song_parts($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);
    $text = preg_replace('/\n{3,}/', '', $text);
    return trim($text);
}

function process_ew_lyrics_line($text_line)
{
    $text_line = process_unicode($text_line);
    $text_line = trim($text_line);
    $text_line = preg_replace("/\{\\\\pard/", '', $text_line);
    if (preg_match("/^\{\\\\/", $text_line)) {
        $text_line = '';
    }
    $text_line = preg_replace("/\\\\par\s?$/", '', $text_line);
    $text_line = preg_replace("/^.*?\s([^\\\\]|$)/", '$1', $text_line);
//    $text_line = preg_replace("/\\\\line .*?fntnamaut /", PHP_EOL, $text_line);
    $text_line = preg_replace("/\\\\line [^ ]+ ([^\\\\])/", PHP_EOL . '$1', $text_line);
    $text_line = str_replace("\line", PHP_EOL . PHP_EOL, $text_line);
    $text_line = str_replace('20}{\*\sdfsdef 20}\sdfsauto ', '', $text_line);
    $text_line = str_replace('72.9}{\*\sdfsdef', '', $text_line);
    $text_line = str_replace('72.9}\sdfsauto', '', $text_line);
    $text_line = str_replace('\qc\qdef\sdewparatemplatestyle101', '', $text_line);
    $text_line = str_replace('\plain\f1\fntnamaut ', '', $text_line);
    $text_line = str_replace('\par', '', $text_line);
    $text_line = preg_replace("/\}$/", '', $text_line);
    $text_line = preg_replace("/\s{2,}/", ' ', $text_line);

    // var_dump($text_line);
    return trim($text_line);
}

function process_ew_lyrics_line_custom($text_line)
{
    $text_line = str_replace(['jesus', 'god', 'gud'], ['Jesus', 'God', 'Gud'], $text_line); // Capitalize some property names
    $text_line = preg_replace('/[.,;]+ ?$/', '', $text_line); // Remove line-ending punctuation
    $text_line = str_replace(['’', '´', '‘', '“', '”'], ["'", "'", "'", '"', '"'], $text_line); // Straighten curly quotes
    $text_line = preg_replace('/(x\d+|\d+x)/i', '', $text_line); // Remove 'x2' references
    $text_line = str_replace(['()', '[]', '[  ]'], '', $text_line); // Remove empty parentheses
    $text_line = ucfirst($text_line); // Always start with a capital
    return trim($text_line);
}

function process_ew_lyrics_line_song_parts($text_line)
{
    if (in_array($text_line, ['Verse', 'VERSE', 'Verse 1', 'Verse1', 'VERSE1', 'VERSE 1'])) {
        $text_line = 'Verse 1';
    }
    if (in_array($text_line, ['Verse2', 'VERSE2', 'VERSE 2'])) {
        $text_line = 'Verse 2';
    }
    if (in_array($text_line, ['Verse3', 'VERSE3', 'VERSE 3'])) {
        $text_line = 'Verse 3';
    }
    if (in_array($text_line, ['Verse4', 'VERSE4', 'VERSE 4'])) {
        $text_line = 'Verse 4';
    }
    if (in_array($text_line, ['Pre-chorus', 'PRE-CHORUS'])) {
        $text_line = 'Pre-Chorus';
    }
    if (in_array($text_line, ['Chorus', 'CHORUS', 'CHORUS 1'])) {
        $text_line = 'Chorus 1';
    }
    if (in_array($text_line, ['Chorus2', 'CHORUS 2'])) {
        $text_line = 'Chorus 2';
    }
    if (in_array($text_line, ['Bridge', 'BRIDGE'])) {
        $text_line = 'Bridge 1';
    }
    if (preg_match('/Tag \d+/', $text_line)) {
        $text_line = 'Tag';
    }
    return $text_line;
}

function save_text_file($song)
{
    global $output_directory;
    $filename = sprintf('%s', $song['title']);
    $filename = str_replace('/', ' -- ', $filename);
    $filename = preg_replace('/\s+/', ' ', trim($filename));
    $filename = process_unicode($filename);
    $filename = str_replace(['%C3', '\ufffd'], ['Å', 'Å'], $filename);
    $file_extension = ".txt";

    $counter = 1;
    while (file_exists($output_directory . $filename . $file_extension)) {
        $filename = trim(preg_replace('/\(\d+\)/', '', $filename));
        $filename .= sprintf(' (%d)', $counter);
        $counter++;
    }

    $contents = $song['text'];
    echo sprintf('Converting "%s" to plain text (filename "%s%s", id: %d)...', $song['title'], $filename, $file_extension, $song['id']) . PHP_EOL;
    file_put_contents($output_directory . $filename . $file_extension, "\xEF\xBB\xBF" . iconv("ISO-8859-1", "UTF-8", iconv("UTF-8", "ISO-8859-1//IGNORE", $contents)));
}

function process_unicode($string)
{
    $string = preg_replace('/\\\\x([0-9A-F]{2})\?/', '&#x$1;', $string);
    $string = preg_replace('/\\\\u(\d+)\?/', '&#$1;', $string);
    return html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
}

$connection_songs = 'sqlite:' . $song_db_path;
$dbh = new PDO('sqlite:' . $song_db_path) or die("cannot open the database");
$dbh_lyrics = new PDO('sqlite:' . $song_words_db_path) or die("cannot open the database");
$query = "SELECT * FROM song WHERE presentation_id IS NULL OR presentation_id != 0";

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