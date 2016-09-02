<?php

mb_detect_order(array('UTF-8', 'ISO-8859-1', 'ASCII'));
ini_set('default_charset', 'utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

/**
 * Formats the title of the song
 *
 * @param $title Raw title from EasyWorship data store
 * @return string Formatted title
 */
function process_ew_title($title)
{
    global $custom_settings;

    $title = trim($title); // We always trim, whether you like it or not!
    $title = preg_replace('/\s+/', ' ', $title); // We also always condense any whitespace characters down to single spaces

    if ($custom_settings['standardize_title_format']) {
        $title = ucwords($title);
        $title = preg_replace("/\s{2,}/", ' ', $title);
        $lc_words = ['and', 'of', 'the'];
        foreach ($lc_words as $lc_word) {
            $title = str_replace(ucwords($lc_word), $lc_word, $title);
        }
        $title = ucfirst($title);
    }

    return $title;
}

/**
 * All processing and formatting for song text
 *
 * @param $text Raw text blob from EasyWorship data store
 * @return string Cleaned and formatted song lyric text
 */
function process_ew_lyrics($text)
{
    // First pass - basic processing
    $text_lines = explode(PHP_EOL, $text);
    foreach ($text_lines as $line_number => &$text_line) {
        $text_line = process_ew_lyrics_line($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);

    // Second loop - makes sure we catch any newlines that were previously marked as "\line" in the original storage
    $text_lines = explode(PHP_EOL, $text);
    foreach ($text_lines as $line_number => &$text_line) {
        $text_line = process_ew_lyrics_line_custom($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);

    $text = preg_replace('/(\n{3,})/', str_repeat(PHP_EOL, 2), $text); // Any time there is a block of newlines > 2, condense it to just two

    return trim($text); // Trim because that's what we do to keep our files clean, and also remove large quantities of newlines at start and end of the block
}

/**
 * Basic processing of text dump from EasyWorship database - cleans all unnecessary formatting data
 *
 * @param $text_line Single line of raw text from EasyWorship data store
 * @return string Cleaned text
 */
function process_ew_lyrics_line($text_line)
{
    $text_line = process_unicode($text_line);
    $text_line = trim($text_line); // Surprise, surprise!
    $text_line = preg_replace("/\{\\\\pard/", '', $text_line); // Remove {\\pard from start of the line

    // Anything that now starts with a '{' is no longer necessary
    if (preg_match("/^\{\\\\/", $text_line)) {
        $text_line = '';
    }
    $text_line = preg_replace("/\\\\par\s?\}?$/", '', $text_line); // Remove \par} from end of the line
    $text_line = preg_replace("/\{\\\\\*\\\\(sdfsreal|sdfsdef) [\d\.]+\}/", '', $text_line); // Remove {\*\sdfsreal 72.9} and {\*\sdfsdef 72.9}
    $text_line = preg_replace("/\\\\[^ ]+( |$)/", '', $text_line); // Remove '\sdslidemarker\qc\qdef\sdewparatemplatestyle101\plain\sdewtemplatestyle101\fs146\sdfsauto' strings
    $text_line = preg_replace("/\}$/", '', $text_line); // Remove any random trailing '}' characters
    $text_line = preg_replace("/\s+/", ' ', $text_line); // Condense any whitespaces (single or multiple) to a single space
    $text_line = preg_replace('/\\\\line ?/', PHP_EOL, $text_line); // Any stray '\line ' symbols should become a newline

    return trim($text_line); // Seeing a recurring theme here?
}

/**
 * Formatting functions according to custom settings array in config
 *
 * @param $text_line Single line of cleaned text
 * @return string Formatted line of text
 */
function process_ew_lyrics_line_custom($text_line)
{
    global $custom_settings;

    // Capitalize some property names
    if ($custom_settings['capitalize_names']) {
        $text_line = str_replace(['jesus', 'god', 'gud'], ['Jesus', 'God', 'Gud'], $text_line);
    }

    // Remove line-ending punctuation
    if ($custom_settings['remove_end_punctuation']) {
        $text_line = preg_replace('/[.,;]+ ?$/', '', $text_line);
    }

    // Straighten curly quotes
    if ($custom_settings['straighten_curly_quotes']) {
        $text_line = str_replace(['’', '´', '‘', '“', '”'], ["'", "'", "'", '"', '"'], $text_line);
    }

    // Remove 'x2' references and empty parentheses
    if ($custom_settings['remove_x2']) {
        $text_line = preg_replace('/(x\d+|\d+x)/i', '', $text_line);
        $text_line = str_replace(['()', '[]', '[  ]'], '', $text_line);
    }

    // Begin all lines with capital letter
    if ($custom_settings['start_with_capital']) {
        $text_line = ucfirst($text_line); // Always start with a capital
    }

    $text_line = trim($text_line); // We trim before the standardized song sections because we might actually want to keep some trailing newlines

    // Standardize the names of the song sections to fit ProPresenter's defaults
    if ($custom_settings['standardize_song_sections']) {
        $text_line = process_ew_lyrics_line_song_parts($text_line);
    }

    return $text_line;
}

/**
 * Standardize the names of the song sections to fit ProPresenter's defaults
 *
 * @param $text_line
 * @return string $text_line
 */
function process_ew_lyrics_line_song_parts($text_line)
{
    $line_breaks = str_repeat(PHP_EOL, 2);

    if (preg_match('/^(Verse|Chorus|Bridge) ?(\d+)$/i', $text_line, $matches)) {
        $text_line = $line_breaks . $matches[1] . ' ' . $matches[2];
    } elseif (preg_match('/^(Verse|Chorus|Bridge)$/i', $text_line, $matches)) {
        $text_line = $line_breaks . $matches[1] . ' 1'; // When just a plain 'Chorus'
    } elseif (preg_match('/^Pre\-?Chorus/i', $text_line, $matches)) {
        $text_line = $line_breaks . 'Pre-Chorus';
    } elseif (preg_match('/^(Tag|Intro) ?\d+?$/i', $text_line, $matches)) {
        $text_line = $line_breaks . $matches[1];
    }
    return $text_line;
}

/**
 * Prepare the metadata to add to the export
 *
 * @param array $song Song array
 * @return string Song metadata
 */
function process_ew_lyrics_metadata(array $song)
{
    $meta_fields = array(
        'title' => 'Title',
        'author' => 'Author',
        'copyright' => 'Copyright',
        'ccli_no' => '',
    );
    $meta_fields_added = 0;
    $metadata = '';

    // Add the fields from the $song array to the meta block
    foreach ($meta_fields as $array_key => $label) {
        if (array_key_exists($array_key, $song)) {
            // The line format is the same for everything except the CCLI Number
            $line_format = '%s: %s';
            if ($array_key === 'ccli_no') {
                $line_format = '%s[S A%s]';
            }

            $metadata .= sprintf($line_format, $label, $song[$array_key]) . PHP_EOL;
            $meta_fields_added++;
        }
    }

    // If we have meta fields, add line breaks to separate from the next section
    if ($meta_fields_added > 0) {
        $metadata .= str_repeat(PHP_EOL, 2);
    }

    return $metadata;
}

/**
 * Worker function that constructs the file contents and saves it to the output directory
 *
 * @param $song Song array
 */
function save_text_file($song)
{
    global $output_directory;
    global $custom_settings;

    $filename = sprintf('%s', $song['title']); // Filename is simply the song's title
    $filename = str_replace('/', ' -- ', $filename);
    $filename = preg_replace('/\s+/', ' ', trim($filename)); // Make sure we don't have any crazy spaces in the file name
    $filename = process_unicode($filename);
    $file_extension = ".txt";

    // Prevent overwriting files - adds a '(1)' style suffix
    if ($custom_settings['prevent_overwrites']) {
        $counter = 1;
        while (file_exists($output_directory . $filename . $file_extension)) {
            $filename = trim(preg_replace('/\(\d+\)/', '', $filename));
            $filename .= sprintf(' (%d)', $counter);
            $counter++;
        }
    }

    // Set up the file contents
    $contents = '';

    // Add the meta data to the top of the file if requested
    if ($custom_settings['add_metadata_to_export_files']) {
        $contents .= process_ew_lyrics_metadata($song);
    }

    $contents .= $song['text'];

    // Desperate attempt to get rid of any lingering unicode formatting issues!
    $contents = iconv("ISO-8859-1", "UTF-8", iconv("UTF-8", "ISO-8859-1//IGNORE", $contents));

    $break_char = php_sapi_name() === "cli" ? PHP_EOL : "<br/>";
    echo sprintf('Converting "%s" to plain text (filename "%s%s", id: %d)...', $song['title'], $filename, $file_extension, $song['id']) . $break_char;

    file_put_contents($output_directory . $filename . $file_extension, "\xEF\xBB\xBF" . $contents);
}

/**
 * Custom function to try to handle various unicode formatting issues that will come our way from EasyWorship
 *
 * @param $string
 * @return string
 */
function process_unicode($string)
{
    $string = preg_replace('/\\\\x([0-9A-F]{2})\?/', '&#x$1;', $string);
    $string = preg_replace('/\\\\u(\d+)\?/', '&#$1;', $string);
    return html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
}