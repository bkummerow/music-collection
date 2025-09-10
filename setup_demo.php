<?php
/**
 * Demo Setup Script
 * Run this once to set up demo data
 */

// Copy demo collection to main collection
$demoFile = __DIR__ . '/data/demo_collection.json';
$mainFile = __DIR__ . '/data/music_collection.json';

if (file_exists($demoFile) && !file_exists($mainFile)) {
    copy($demoFile, $mainFile);
    echo "Demo collection loaded successfully!";
} else {
    echo "Demo collection already exists or demo file not found.";
}
?>
