<?php
require_once __DIR__ . '/../services/AlbumPersonalFields.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('Near Mint (NM or M-)') === 'Near Mint (NM or M-)', 'valid grade');
assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('Bogus') === '', 'invalid grade empty');
assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('  ') === '', 'blank empty');

$existing = [
    'media_condition' => 'Very Good (VG)',
    'sleeve_condition' => '',
    'notes' => 'local note',
];
$draft = [
    'media_condition' => 'Mint (M)',
    'sleeve_condition' => '',
    'notes' => '',
];
$merged = AlbumPersonalFields::mergeFromDiscogsDraft($existing, $draft, 'collection');
assert_true($merged['media_condition'] === 'Mint (M)', 'non-empty Discogs overwrites media');
assert_true($merged['sleeve_condition'] === '', 'empty Discogs sleeve leaves local empty');
assert_true($merged['notes'] === 'local note', 'empty Discogs notes leave local');
assert_true($merged['changed'] === true, 'changed when media updated');

$want = AlbumPersonalFields::mergeFromDiscogsDraft(
    ['media_condition' => 'Mint (M)', 'sleeve_condition' => 'Mint (M)', 'notes' => ''],
    ['media_condition' => 'Poor (P)', 'sleeve_condition' => 'Poor (P)', 'notes' => 'want note'],
    'wantlist'
);
assert_true($want['media_condition'] === 'Mint (M)', 'wantlist ignores Discogs media');
assert_true($want['sleeve_condition'] === 'Mint (M)', 'wantlist ignores Discogs sleeve');
assert_true($want['notes'] === 'want note', 'wantlist applies notes');

echo "discogs_condition_notes_sync_test: OK\n";
