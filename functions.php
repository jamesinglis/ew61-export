<?php

/**
 * Processing functions for EasyWorship 6.1 Exporter
 *
 * @package    EasyWorship 6.1 Exporter
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author     James Inglis <hello@jamesinglis.no>
 * @version    0.2
 * @link       https://github.com/jamesinglis/ew61-export
 */


// Set the file encoding for everything to UTF-8
mb_detect_order(array('UTF-8', 'ISO-8859-1', 'ASCII'));
ini_set('default_charset', 'utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

define('EW6_EXPORT_EOL', "\r\n");

// Output files in a timestamp labelled sub-directory
if ($custom_settings['output_subdirectory']) {
    $output_directory = __DIR__ . '/output/' . date('YmdHis') . '_' . $file_export_type . '/';
    mkdir($output_directory, 0777, true);
}


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
    global $custom_settings;

    // Process as much of the RTF content as possible before we break up into lines
    $text = process_unicode($text);
    $text = rtf2text($text);
    $text = str_replace(array('\r\n', '\r', '\n'), PHP_EOL, $text);

    // First pass - basic processing
    $text_lines = explode(PHP_EOL, $text);
    foreach ($text_lines as $line_number => &$text_line) {
        $text_line = process_ew_lyrics_line($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);

    // Second loop - makes sure we catch any newlines that were previously marked as "\line" in the original storage
    $text_lines = explode(PHP_EOL, trim($text));
    foreach ($text_lines as $line_number => &$text_line) {
        $text_line = process_ew_lyrics_line_custom($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);

    // Remove any double line breaks - common with the RTF input
    $text = preg_replace('/(?<=[^\n])\n{2}(?=[^\n])/', PHP_EOL, trim($text));

    // Condense all slide breaks (removes all paragraph breaks - will add in relevant newlines later)
    $line_breaks_to_condense = 3;
    $condensed_line_breaks = 2;
    if ($custom_settings['condense_slide_breaks']) {
        $line_breaks_to_condense--;
        $condensed_line_breaks--;
    }

    $text = preg_replace('/(\n{' . $line_breaks_to_condense . ',})/', str_repeat(PHP_EOL, $condensed_line_breaks), trim($text)); // Any time there is a block of newlines > 2, condense it to just two

    // Third loop
    $text_lines = explode(PHP_EOL, $text);
    foreach ($text_lines as $line_number => &$text_line) {
        $text_line = process_ew_lyrics_line_song_parts($text_line);
    }
    unset($text_line);
    $text = implode(PHP_EOL, $text_lines);

    $line_breaks_to_condense = 3;
    $condensed_line_breaks = 2;
    $text = preg_replace('/(\n{' . $line_breaks_to_condense . ',})/', str_repeat(PHP_EOL, $condensed_line_breaks), trim($text)); // Any time there is a block of newlines > 2, condense it to just two

    return process_ew_blocks($text); // Trim because that's what we do to keep our files clean, and also remove large quantities of newlines at start and end of the block
}

/**
 * Basic processing of text dump from EasyWorship database - cleans all unnecessary formatting data
 * This used to manually strip out RTF details - now we just clean up the remnants
 *
 * @param $text_line Single line of raw text from EasyWorship data store
 * @return string Cleaned text
 */
function process_ew_lyrics_line($text_line)
{
    $text_line = process_unicode($text_line);
    $text_line = trim($text_line);
    $text_line = preg_replace('/^\.$/', PHP_EOL, $text_line);
    $text_line = preg_replace('/^(\(|\))$/', PHP_EOL, $text_line);
    $text_line = preg_replace('/^\(\)$/', PHP_EOL, $text_line);
    $text_line = preg_replace('/(Arial|Tahoma);/', PHP_EOL, $text_line);

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
    global $words_to_capitalize;

    // Capitalize some property names
    if ($custom_settings['capitalize_names']) {
        foreach ($words_to_capitalize as $word) {
            $text_line = preg_replace('/( |^)(' . $word . ')( |$)/i', '$1' . $word . '$3', $text_line);
        }
    }

    // Remove line-ending punctuation
    if ($custom_settings['remove_end_punctuation']) {
        $text_line = preg_replace('/[.,;]+ ?$/', '', $text_line);
    }

    // Fix mid-line punctuation issues
    if ($custom_settings['fix_mid_line_punctuation']) {
        $text_line = preg_replace('/\./', "\n", $text_line);
        $text_line = preg_replace('/([,;\?!])([^ ])/', '$1 $2', $text_line);
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
    global $custom_settings;
    global $song_section_names;;

    $line_breaks = str_repeat(PHP_EOL, 2);

    // Standardize the names of the song sections to fit ProPresenter's defaults
    if ($custom_settings['standardize_song_sections']) {
        if (preg_match('/^(Verse|Chorus|Bridge)\s?(\d+)$/i', $text_line, $matches)) {
            $text_line = ucwords(strtolower($matches[1])) . ' ' . $matches[2];
        } elseif (preg_match('/^(Verse|Chorus|Bridge)$/i', $text_line, $matches)) {
            $text_line = ucwords(strtolower($matches[1])) . ' 1'; // When just a plain 'Chorus'
        } elseif (preg_match('/^Pre\-?Chorus/i', $text_line, $matches)) {
            $text_line = 'Pre-Chorus';
        } elseif (preg_match('/^(Tag|Intro) ?\d+?$/i', $text_line, $matches)) {
            $text_line = ucwords(strtolower($matches[1]));
        }
    }

    // Add double line break before each song part
    if (preg_match('/^(' . implode('|', $song_section_names) . ')/i', $text_line, $matches)) {
        $text_line = $line_breaks . $text_line;
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

function process_ew_blocks($text_lines)
{
    $text_blocks = explode(str_repeat(PHP_EOL, 2), trim($text_lines));
    $text_blocks_array = array();
    $text_blocks_output_array = array();

    foreach ($text_blocks as &$text_block) {
        $text_block_array = process_ew_single_block($text_block);
        $text_blocks_array[] = $text_block_array;
        $text_blocks_output_array[] = $text_block_array['output'];
    }
    unset($text_block);

    return implode(str_repeat(PHP_EOL, 2), $text_blocks_output_array);
}

function process_ew_single_block($text_block)
{
    global $custom_settings;
    global $song_section_names;
    global $reflow_max_lines;

    $text_lines = explode(PHP_EOL, $text_block);
    $text_block_array['heading'] = false;

    if (preg_match('/(' . implode('|', $song_section_names) . ') ?(\d+?)/', $text_lines[0], $matches)) {
        $text_block_array['heading'] = array(
            'label' => $matches[0],
            'type' => $matches[1],
            'number' => $matches[2],
        );
        unset($text_lines[0]);
    }

    $text_block_array['lines'] = array_values($text_lines);
    $text_block_array['line_count'] = count($text_lines);

    $group = 0;
    $group_size = count($text_lines);
    if ($custom_settings['reflow_large_blocks']) {
        $group_size = $reflow_max_lines;
    }

    // If there isn't an even split with the $reflow_max_lines number of lines, see if one line less helps
    if ($group_size > 2 && count($text_lines) % $group_size !== 0 && count($text_lines) % ($group_size - 1) === 0) {
        $group_size = $group_size - 1;
    }

    // Loop through the
    for ($i = 0; $i < count($text_lines); $i++) {
        $text_block_array['groups'][$group][] = $text_block_array['lines'][$i];
        if (($i + 1) % $group_size === 0) {
            $group++;
        }
    }

    $temp_group_array = array();

    if (array_key_exists('groups', $text_block_array) && count($text_block_array) > 0) {
        foreach ($text_block_array['groups'] as $group) {
            $temp_group_array[] = implode(str_repeat(PHP_EOL, 1), $group);
        }
    }

    // Build up the output by condensing arrays
    $text_block_array['output'] = $text_block_array['heading'] !== false ? $text_block_array['heading']['label'] . PHP_EOL : '';
    $text_block_array['output'] .= implode(str_repeat(PHP_EOL, 2), $temp_group_array);

    return $text_block_array;
}

/**
 * Worker function that constructs the file contents and saves it to the output directory
 *
 * @param $song Song array
 */
function save_text_file($song, $file_export_type)
{
    global $output_directory;
    global $custom_settings;

    $filename = sprintf('%s', $song['title']); // Filename is simply the song's title
    $filename = str_replace('/', ' -- ', $filename);
    $filename = preg_replace('/\s+/', ' ', trim($filename)); // Make sure we don't have any crazy spaces in the file name
    $filename = process_unicode($filename);
    $file_extension = ".txt";
    if ($file_export_type === "propresenter6") {
        $file_extension = '.pro6';
    }

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

    if ($file_export_type === "propresenter6") {
        $contents .= $song['text'];
        $break_char = php_sapi_name() === "cli" ? PHP_EOL : "<br/>";
        echo sprintf('Converting "%s" to ProPresenter6 (filename "%s%s", id: %d)...', $song['title'], $filename, $file_extension, $song['id']) . $break_char;
    } else {
        // Add the meta data to the top of the file if requested
        if ($custom_settings['add_metadata_to_export_files']) {
            $contents .= process_ew_lyrics_metadata($song);
        }

        $contents .= $song['text'];

        if ($custom_settings['aggressive_text_encoding']) {
            // Desperate attempt to get rid of any lingering unicode formatting issues! Forces text to ISO-8859-1 character set
            $contents = iconv("ISO-8859-1", "UTF-8", iconv("UTF-8", "ISO-8859-1//IGNORE", $contents));
        }

        $break_char = php_sapi_name() === "cli" ? PHP_EOL : "<br/>";
        echo sprintf('Converting "%s" to plain text (filename "%s%s", id: %d)...', $song['title'], $filename, $file_extension, $song['id']) . $break_char;
    }

    file_put_contents($output_directory . $filename . $file_extension, "\xEF\xBB\xBF" . str_replace(PHP_EOL, EW6_EXPORT_EOL, $contents));
}

function generateRandomHex($number = 1)
{
    return strtoupper(substr(bin2hex(openssl_random_pseudo_bytes($number)), 0, $number));
}

function convert_non_ascii_chars_to_hex($text)
{
    $text = utf8_decode(mb_convert_encoding($text, "UTF-8"));
    $characters = utf8_decode(mb_convert_encoding("æøåÆØÅ", "UTF-8"));
    foreach (str_split($characters) as $char) {
        $converted_char = '\\\'' . bin2hex($char);
        $text = str_replace($char, $converted_char, $text);
    }
    return $text;
}

function generate_propresenter_guid()
{
    // UUID Format: 94A8AD6C-2A51-44ED-9CAF-79DE1B722190
    return sprintf('%s-%s-%s-%s', generateRandomHex(8), generateRandomHex(4), generateRandomHex(4), generateRandomHex(12));
}

function get_propresenter_section_color($section_name)
{
    $color_map = array(
        'Intro' => '0 0 0 1',
        'Verse 1' => '0 0 1 1',
        'Verse 2' => '0 0.501960814 1 1',
        'Verse 3' => '0 1 1 1',
        'Verse 4' => '0 1 0.501960814 1',
        'Verse 5' => '0 1 0 1',
        'Verse 6' => '0.501960814 1 0 1',
        'Pre-Chorus' => '1 0.400000006 0.400000006 1',
        'Chorus 1' => '1 0 0 1',
        'Chorus 2' => '0.501960814 0 0 1',
        'Chorus 3' => '0.501960814 0 0.250980407 1',
        'Bridge 1' => '0.501960814 0 1 1',
        'Bridge 2' => '0.8000000119 0.400000006 1 1',
        'Tag' => '0 0 0 1',
        'End' => '0 0 0 1',
    );

    if (array_key_exists($section_name, $color_map)) {
        return $color_map[$section_name];
    }
    return '';
}

function get_propresenter_section_hotkey($section_name)
{
    $hotkey_map = array(
        'Intro' => 'I',
        'Verse 1' => 'A',
        'Verse 2' => 'S',
        'Verse 3' => 'D',
        'Verse 4' => 'F',
        'Verse 5' => 'G',
        'Verse 6' => 'H',
        'Pre-Chorus' => 'X',
        'Chorus 1' => 'C',
        'Chorus 2' => 'V',
        'Chorus 3' => '',
        'Bridge 1' => 'B',
        'Bridge 2' => 'M',
        'Tag' => 'N',
        'End' => 'Z',
    );

    if (array_key_exists($section_name, $hotkey_map)) {
        return strtolower($hotkey_map[$section_name]);
    }
    return '';
}

/**
 * Worker function that constructs the file contents and saves it to the output directory
 *
 * @param $song Song array
 */
function generate_prop6_file_contents($song)
{
    global $custom_settings;
    $prop6_file_template = file_get_contents(dirname(__FILE__) . '/resources/propresenter6_file_wrapper.xml');
    $prop6_group_template = file_get_contents(dirname(__FILE__) . '/resources/propresenter6_group_element.xml');
    $prop6_slide_template = file_get_contents(dirname(__FILE__) . '/resources/propresenter6_slide_element.xml');
    $prop6_slide_text = file_get_contents(dirname(__FILE__) . '/resources/propresenter6_slide_text.txt');

    $slide_elements = array();

    if ($custom_settings['prop6_add_blank_intro'] == true) {
        $slide_elements['Intro'] = array(
            'name' => 'Intro',
            'guid' => generate_propresenter_guid(),
            'color' => get_propresenter_section_color('Intro'),
            'hotkey' => get_propresenter_section_hotkey('Intro'),
            'slides' => array(
                array(
                    'guid' => generate_propresenter_guid(),
                    'text' => ''
                )
            )
        );
    }

    $section_counter = 1;
    $current_group_name = 'Verse ' . $section_counter;
    $slide_break = false;
    $has_groups = false;
    if (preg_match('/(Verse|Chorus|Bridge)/i', $song['text'], $matches)) {
        $has_groups = true;
    }

    $text_lines = explode(PHP_EOL, $song['text']);

    // Loop through the lines
    foreach ($text_lines as $text_line) {

        if ($has_groups === false && empty($text_line)) {
            $section_counter++;
            $current_group_name = 'Verse ' . $section_counter;
            $slide_break = true;
            continue;
        } elseif ($has_groups === true && empty($text_line)) {
            $slide_break = true;
            continue;
        }

        // Create a new group whenever a heading is found
        if (preg_match('/^(Verse|Chorus|Bridge)\s?(\d+)$/i', $text_line, $matches)) {
            $current_group_name = ucwords(strtolower($matches[1])) . ' ' . $matches[2];
        } elseif (preg_match('/^(Verse|Chorus|Bridge)$/i', $text_line, $matches)) {
            $current_group_name = ucwords(strtolower($matches[1])) . ' 1'; // When just a plain 'Chorus'
        } elseif (preg_match('/^Pre\-?Chorus/i', $text_line, $matches)) {
            $current_group_name = 'Pre-Chorus';
        } elseif (preg_match('/^(Tag|Intro) ?\d?$/i', $text_line, $matches)) {
            $current_group_name = ucwords(strtolower($matches[1]));
        }

        if (array_key_exists($current_group_name, $slide_elements) === false) {
            $slide_elements[$current_group_name] = array(
                'name' => $current_group_name,
                'guid' => generate_propresenter_guid(),
                'color' => get_propresenter_section_color($current_group_name),
                'hotkey' => get_propresenter_section_hotkey($current_group_name),
                'slides' => array()
            );
            continue;
        }

        // Create a slide for every chunk of lines
        if ($slide_break || count($slide_elements[$current_group_name]['slides']) === 0) {
            $slide_elements[$current_group_name]['slides'][] = array(
                'guid' => generate_propresenter_guid(),
                'text' => $text_line
            );
        } else {
            $slide_index = count($slide_elements[$current_group_name]['slides']) - 1;
            $slide_elements[$current_group_name]['slides'][$slide_index]['text'] .= PHP_EOL . $text_line;
        }

        $slide_break = false;
    }

    if ($custom_settings['prop6_add_blank_end'] == true) {
        $slide_elements['End'] = array(
            'name' => 'End',
            'guid' => generate_propresenter_guid(),
            'color' => get_propresenter_section_color('End'),
            'hotkey' => get_propresenter_section_hotkey('End'),
            'slides' => array(
                array(
                    'guid' => generate_propresenter_guid(),
                    'text' => ''
                )
            )
        );
    }

    $groups_xml = '';
    foreach ($slide_elements as $group) {
        $slides_xml = '';
        foreach ($group['slides'] as $index => $slide) {
            $slide_build = $prop6_slide_template;
            if ($index === 0 && $custom_settings['prop6_add_hotkeys'] == true) {
                $slide_build = str_replace('%%HOTKEY%%', $group['hotkey'], $slide_build);
            } else {
                $slide_build = str_replace('%%HOTKEY%%', '', $slide_build);
            }
            $slide_build = str_replace('%%GUID%%', $slide['guid'], $slide_build);
            $slide_build = str_replace('%%TEXTGUID%%', generate_propresenter_guid(), $slide_build);
            $slide_text = str_replace(PHP_EOL, '\\' . PHP_EOL, $slide['text']);
            $slide_text = convert_non_ascii_chars_to_hex($slide_text);
            $slide_text = str_replace('%%TEXT%%', $slide_text, $prop6_slide_text);
            $slide_build = str_replace('%%TEXT%%', base64_encode($slide_text), $slide_build);
            $slides_xml .= $slide_build;
        }

        $group_build = $prop6_group_template;
        $group_build = str_replace('%%NAME%%', $group['name'], $group_build);
        $group_build = str_replace('%%COLOR%%', $group['color'], $group_build);
        $group_build = str_replace('%%GUID%%', $group['guid'], $group_build);
        $group_build = str_replace('%%SLIDES%%', $slides_xml, $group_build);
        $groups_xml .= $group_build;
    }

    $prop6_file_template = str_replace('%%TITLE%%', $song['title'], $prop6_file_template);
    $prop6_file_template = str_replace('%%GUID%%', generate_propresenter_guid(), $prop6_file_template);
    $prop6_file_template = str_replace('%%GROUPS%%', $groups_xml, $prop6_file_template);

    return $prop6_file_template;
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

/* Functions adapted from http://webcheatsheet.com/php/reading_the_clean_text_from_rtf.php */

function rtf_isPlainText($s)
{
    $arrfailAt = array("*", "fonttbl", "colortbl", "datastore", "themedata");
    for ($i = 0; $i < count($arrfailAt); $i++)
        if (!empty($s[$arrfailAt[$i]])) return false;
    return true;
}

function rtf2text($text)
{
    if (!strlen($text))
        return "";

    // Create empty stack array.
    $document = "";
    $stack = array();
    $j = -1;
    // Read the data character-by- character…
    for ($i = 0, $len = strlen($text); $i < $len; $i++) {
        $c = $text[$i];

        // Depending on current character select the further actions.
        switch ($c) {
            // the most important key word backslash
            case "\\":
                // read next character
                $nc = $text[$i + 1];

                // If it is another backslash or nonbreaking space or hyphen,
                // then the character is plain text and add it to the output stream.
                if ($nc == '\\' && rtf_isPlainText($stack[$j])) $document .= '\\';
                elseif ($nc == '~' && rtf_isPlainText($stack[$j])) $document .= ' ';
                elseif ($nc == '_' && rtf_isPlainText($stack[$j])) $document .= '-';
                // If it is an asterisk mark, add it to the stack.
                elseif ($nc == '*') $stack[$j]["*"] = true;
                // If it is a single quote, read next two characters that are the hexadecimal notation
                // of a character we should add to the output stream.
                elseif ($nc == "'") {
                    $hex = substr($text, $i + 2, 2);
                    if (rtf_isPlainText($stack[$j]))
                        $document .= html_entity_decode("&#" . hexdec($hex) . ";");
                    //Shift the pointer.
                    $i += 2;
                    // Since, we’ve found the alphabetic character, the next characters are control word
                    // and, possibly, some digit parameter.
                } elseif ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                    $word = "";
                    $param = null;

                    // Start reading characters after the backslash.
                    for ($k = $i + 1, $m = 0; $k < strlen($text); $k++, $m++) {
                        $nc = $text[$k];
                        // If the current character is a letter and there were no digits before it,
                        // then we’re still reading the control word. If there were digits, we should stop
                        // since we reach the end of the control word.
                        if ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                            if (empty($param))
                                $word .= $nc;
                            else
                                break;
                            // If it is a digit, store the parameter.
                        } elseif ($nc >= '0' && $nc <= '9')
                            $param .= $nc;
                        // Since minus sign may occur only before a digit parameter, check whether
                        // $param is empty. Otherwise, we reach the end of the control word.
                        elseif ($nc == '-') {
                            if (empty($param))
                                $param .= $nc;
                            else
                                break;
                        } else
                            break;
                    }
                    // Shift the pointer on the number of read characters.
                    $i += $m - 1;

                    // Start analyzing what we’ve read. We are interested mostly in control words.
                    $toText = "";
                    switch (strtolower($word)) {
                        // If the control word is "u", then its parameter is the decimal notation of the
                        // Unicode character that should be added to the output stream.
                        // We need to check whether the stack contains \ucN control word. If it does,
                        // we should remove the N characters from the output stream.
                        case "u":
                            $toText .= html_entity_decode("&#x" . dechex($param) . ";");
                            $ucDelta = @$stack[$j]["uc"];
                            if ($ucDelta > 0)
                                $i += $ucDelta;
                            break;
                        // Select line feeds, spaces and tabs.
                        case "par":
                        case "page":
                        case "column":
                        case "line":
                        case "lbr":
                            $toText .= "\n";
                            break;
                        case "emspace":
                        case "enspace":
                        case "qmspace":
                            $toText .= " ";
                            break;
                        case "tab":
                            $toText .= "\t";
                            break;
                        // Add current date and time instead of corresponding labels.
                        case "chdate":
                            $toText .= date("m.d.Y");
                            break;
                        case "chdpl":
                            $toText .= date("l, j F Y");
                            break;
                        case "chdpa":
                            $toText .= date("D, j M Y");
                            break;
                        case "chtime":
                            $toText .= date("H:i:s");
                            break;
                        // Replace some reserved characters to their html analogs.
                        case "emdash":
                            $toText .= html_entity_decode("&mdash;");
                            break;
                        case "endash":
                            $toText .= html_entity_decode("&ndash;");
                            break;
                        case "bullet":
                            $toText .= html_entity_decode("&#149;");
                            break;
                        case "lquote":
                            $toText .= html_entity_decode("&lsquo;");
                            break;
                        case "rquote":
                            $toText .= html_entity_decode("&rsquo;");
                            break;
                        case "ldblquote":
                            $toText .= html_entity_decode("&laquo;");
                            break;
                        case "rdblquote":
                            $toText .= html_entity_decode("&raquo;");
                            break;
                        // Add all other to the control words stack. If a control word
                        // does not include parameters, set &param to true.
                        default:
                            $stack[$j][strtolower($word)] = empty($param) ? true : $param;
                            break;
                    }
                    // Add data to the output stream if required.
                    if (array_key_exists($j, $stack) && rtf_isPlainText($stack[$j]))
                        $document .= $toText;
                }

                $i++;
                break;
            // If we read the opening brace {, then new subgroup starts and we add
            // new array stack element and write the data from previous stack element to it.
            case "{":
                $j++;
                if (array_key_exists($j, $stack)) {
                    array_push($stack, $stack[$j]);
                }
                break;
            // If we read the closing brace }, then we reach the end of subgroup and should remove
            // the last stack element.
            case "}":
                array_pop($stack);
                $j--;
                break;
            // Skip “trash”.
            case '\0':
            case '\r':
            case '\f':
            case '\n':
                break;
            // Add other data to the output stream if required.
            default:
                if (array_key_exists($j, $stack) && rtf_isPlainText($stack[$j]))
                    $document .= $c;
                break;
        }
    }
    // Return result.
    return $document;
}