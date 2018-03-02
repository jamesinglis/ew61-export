<?php

$test_mode = false; // Only process the first 10 songs in the database
$test_single_song_id = false;

$song_db_path = __DIR__ . '/databases/Songs.db';
$song_words_db_path = __DIR__ . '/databases/SongWords.db';
$output_directory = __DIR__ . '/output/';

// Custom Settings
$custom_settings = array(
    'capitalize_names' => true, // Capitalize some property names
    'remove_end_punctuation' => true, // Remove line-ending punctuation
    'fix_mid_line_punctuation' => true, // Fix mid-line punctuation
    'straighten_curly_quotes' => true, // Straighten curly quotes
    'remove_x2' => true, // Remove 'x2' type references and empty parentheses
    'start_with_capital' => true, // Begin all lines with capital letter
    'standardize_song_sections' => true, // Standardize the names of the song sections to fit ProPresenter's defaults
    'standardize_title_format' => true, // Standardize the formatting of the name of the songs
    'prevent_overwrites' => true, // Prevent overwriting files - adds a '(1)' style suffix
    'add_metadata_to_export_files' => true, // Adds the metadata block to the top of the export files
    'condense_slide_breaks' => true, // Condense all slide breaks
    'reflow_large_blocks' => true, // Reflow large blocks
    'output_subdirectory' => true, // Output files in a timestamp labelled sub-directory
    'prop6_add_blank_intro' => true, // Adds a blank "Intro" slide to ProPresenter files
    'prop6_add_blank_end' => true, // Adds a blank "End" slide to ProPresenter files
    'prop6_add_hotkeys' => true, // Adds a blank "End" slide to ProPresenter files
);

$file_export_type = 'plain_text'; // set to 'propresenter6' to use the experimental ProPresenter6 output

// ProPresenter6 export settings
$reflow_max_lines = 2;
$song_section_names = array('Verse', 'Chorus', 'Pre-Chorus', 'Bridge', 'Tag', 'Intro');
$words_to_capitalize = array('Jesus', 'God', 'Gud', 'Lord', 'You', 'Your', 'Du', 'Din', 'Ditt', 'Han', 'Hans', 'Ham', 'Holy Spirit', 'Father');