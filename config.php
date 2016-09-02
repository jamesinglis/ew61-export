<?php

$test_mode = false; // Only process the first 10 songs in the database

$song_db_path = __DIR__ . '/databases/Songs.db';
$song_words_db_path = __DIR__ . '/databases/SongWords.db';
$output_directory = __DIR__ . '/output/';

// Custom Settings
$custom_settings = array(
    'capitalize_names' => true, // Capitalize some property names
    'remove_end_punctuation' => true, // Remove line-ending punctuation
    'straighten_curly_quotes' => true, // Straighten curly quotes
    'remove_x2' => true, // Remove 'x2' type references and empty parentheses
    'start_with_capital' => true, // Begin all lines with capital letter
    'standardize_song_sections' => true, // Standardize the names of the song sections to fit ProPresenter's defaults
    'standardize_title_format' => true, // Standardize the formatting of the name of the songs
    'prevent_overwrites' => true, // Prevent overwriting files - adds a '(1)' style suffix
    'add_metadata_to_export_files' => true, // Adds the metadata block to the top of the export files
);