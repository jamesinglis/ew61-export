<?php

$test_mode = false; // Only process the first 10 songs in the database
$test_single_song_id = false; // Specify if you only want to convert a single song

$song_db_path = __DIR__ . '/databases/Songs.db';
$song_words_db_path = __DIR__ . '/databases/SongWords.db';
$output_directory = __DIR__ . '/output/';

// Custom Settings
$custom_settings = array(
    'capitalize_names' => false, // Capitalize some property names
    'remove_end_punctuation' => false, // Remove line-ending punctuation
    'fix_mid_line_punctuation' => false, // Fix mid-line punctuation
    'straighten_curly_quotes' => false, // Straighten curly quotes
    'remove_x2' => false, // Remove 'x2' type references and empty parentheses
    'start_with_capital' => false, // Begin all lines with capital letter
    'standardize_song_sections' => false, // Standardize the names of the song sections to fit ProPresenter's defaults
    'standardize_title_format' => false, // Standardize the formatting of the name of the songs
    'prevent_overwrites' => true, // Prevent overwriting files - adds a '(1)' style suffix
    'add_metadata_to_export_files' => true, // Adds the metadata block to the top of the export files
    'condense_slide_breaks' => false, // Condense all slide breaks
    'reflow_large_blocks' => false, // Reflow large blocks
    'output_subdirectory' => true, // Output files in a timestamp labelled sub-directory
    'aggressive_text_encoding' => false, // Aggressively convert songs to ISO-8859-1 character set - this will most likely break songs with non-Latin characters!
    'prop6_add_blank_intro' => false, // Adds a blank "Intro" slide to ProPresenter files
    'prop6_add_blank_end' => false, // Adds a blank "End" slide to ProPresenter files
    'prop6_add_hotkeys' => false, // Adds hot keys to ProPresenter files
);

$reflow_max_lines = 2; // How many lines should we try to 'reflow' the text to?
$file_export_type = 'plain_text'; // set to 'propresenter6' to use the experimental ProPresenter6 output

$song_section_names = array('Verse', 'Chorus', 'Pre-Chorus', 'Bridge', 'Tag', 'Intro');
$words_to_capitalize = array('Jesus', 'God', 'Gud', 'Lord', 'You', 'Your', 'Du', 'Din', 'Ditt', 'Han', 'Hans', 'Ham', 'Holy Spirit', 'Father');