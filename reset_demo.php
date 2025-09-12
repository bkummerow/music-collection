<?php
/**
 * Demo Reset Script
 * Resets the demo to its original state
 */

// Set proper headers
header('Content-Type: application/json');

// Only allow this to run in demo mode
if (!isset($_ENV['DEMO_MODE']) || $_ENV['DEMO_MODE'] !== 'true') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Demo reset not available']);
    exit;
}

// Reset password to default
$authConfig = [
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'session_timeout' => 3600
];

file_put_contents(__DIR__ . '/config/auth_config.php', '<?php
$auth_config = ' . var_export($authConfig, true) . ';');

// Reset demo data
$demoData = [
    'albums' => [
        '0' => [
            'id' => 1,
            'artist_name' => 'Radiohead',
            'album_name' => 'OK Computer',
            'release_year' => '1997',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/pfroyXpbmYcY-VAwtehfWCJTkFp846Z83468DHSuckY/rs:fit/g:sm/q:90/h:591/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTIzNzIz/MTk4LTE3MTkwNzg1/MDktMTM2Mi5qcGVn.jpeg',
            'discogs_release_id' => 23723198,
            'created_date' => '2025-08-11 13:17:15',
            'updated_date' => '2025-09-04 01:14:00',
            'style' => 'Alternative Rock',
            'format' => 'Vinyl,LP,Album,Reissue,Stereo',
            'artist_type' => 'Group',
            'label' => 'XL Recordings',
            'producer' => 'Andi Watson'
        ],
        '1' => [
            'id' => 2,
            'artist_name' => 'Scrawl',
            'album_name' => 'Velvet Hammer',
            'release_year' => '1993',
            'is_owned' => 0,
            'want_to_own' => 1,
            'cover_url' => 'https://i.discogs.com/oY5SURjJtbTVqheVz8uy1u3YVfOSnvUu1rAO9kaxFQE/rs:fit/g:sm/q:90/h:583/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTY0Nzc5/NC0xNjkwNDE2ODIx/LTczNzcuanBlZw.jpeg',
            'discogs_release_id' => 647794,
            'created_date' => '2025-08-11 13:17:52',
            'updated_date' => '2025-09-04 01:14:16',
            'style' => 'Indie Rock',
            'format' => 'Vinyl,LP,Album',
            'artist_type' => 'Group',
            'label' => 'Simple Machines',
            'producer' => 'Steve Albini'
        ],
        '2' => [
            'id' => 3,
            'artist_name' => 'The Velvet Underground',
            'album_name' => 'The Velvet Underground & Nico',
            'release_year' => '1967',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/e0ZjfF8_hxL_mo0Ci60dgrRYtkx2ILwTux4btdFDgYk/rs:fit/g:sm/q:90/h:610/w:599/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE2OTU0/MDQxLTE2MTQzOTEy/NDEtNjg3Ni5qcGVn.jpeg',
            'discogs_release_id' => 16954041,
            'created_date' => '2025-08-11 13:18:22',
            'updated_date' => '2025-09-04 01:15:07',
            'style' => 'Art Rock, Psychedelic Rock, Experimental',
            'format' => 'Vinyl,LP,Album,Reissue,Stereo',
            'artist_type' => 'Group',
            'label' => 'Verve Records',
            'producer' => 'Andy Warhol'
        ],
        '10' => [
            'id' => 11,
            'artist_name' => 'R.E.M.',
            'album_name' => 'Reckoning',
            'release_year' => '1984',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/w7m6c1jYOb3gNlhadAZuRziQxOuWb3VsXyOv2nRZRLg/rs:fit/g:sm/q:90/h:603/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTQxNDcy/MC0xNDI1NjgwNjY4/LTgzNzcuanBlZw.jpeg',
            'discogs_release_id' => 414720,
            'style' => 'Alternative Rock, Indie Rock, Jangle Pop',
            'created_date' => '2025-08-22 19:30:52',
            'updated_date' => '2025-09-04 01:13:40',
            'format' => 'Vinyl,LP,Album',
            'artist_type' => 'Group',
            'label' => 'I.R.S. Records',
            'producer' => 'Don Dixon, Mitch Easter'
        ],
        '17' => [
            'id' => 21,
            'artist_name' => 'Archers Of Loaf',
            'album_name' => 'The Loaf\'s Revenge',
            'release_year' => '1993',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/b2RhMXV-ted0qpbV0bTJG-3qmDoT6r0rSLH79Z_7YYA/rs:fit/g:sm/q:90/h:595/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTUyNDM5/NS0xMTU4MDY1MDQy/LmpwZWc.jpeg',
            'discogs_release_id' => 524395,
            'style' => 'Indie Rock',
            'created_date' => '2025-08-23 19:16:53',
            'updated_date' => '2025-09-04 01:11:53',
            'format' => 'Vinyl,7",45 RPM',
            'artist_type' => 'Group',
            'label' => 'Alias',
            'producer' => 'Archers Of Loaf, Caleb Southern'
        ],
        '19' => [
            'id' => 24,
            'artist_name' => 'Elvis Costello & The Attractions',
            'album_name' => 'This Year\'s Model',
            'release_year' => '1978',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/PthUds0tVy-dMR5zrUTikE3cKxwMhd80fnEco9HPvRg/rs:fit/g:sm/q:90/h:600/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE5MzAz/NDE0LTE2MjQ4NzM1/NzEtNDM1NC5qcGVn.jpeg',
            'discogs_release_id' => 19303414,
            'style' => 'New Wave, Power Pop, Punk',
            'created_date' => '2025-08-23 22:09:49',
            'updated_date' => '2025-09-04 01:12:59',
            'format' => 'Vinyl,LP,Album,Stereo',
            'artist_type' => 'Group',
            'label' => 'Radar Records (5)',
            'producer' => 'Nick Lowe'
        ],
        '29' => [
            'id' => 43,
            'artist_name' => 'David Bowie',
            'album_name' => 'Low',
            'release_year' => '1977',
            'is_owned' => 0,
            'want_to_own' => 1,
            'cover_url' => 'https://i.discogs.com/fcd6DN-Egh92gVBfcYbkpz6xDE10IPb2D-wBnu3mpS8/rs:fit/g:sm/q:90/h:587/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTExNTk3/MTEyLTE1MTkzMTY1/NzctODEzNS5qcGVn.jpeg',
            'discogs_release_id' => 11597112,
            'style' => 'Art Rock, Experimental, Ambient',
            'format' => 'Vinyl,LP,Album,Reissue,Remastered',
            'artist_type' => 'Person',
            'created_date' => '2025-08-30 16:36:14',
            'updated_date' => '2025-09-04 01:12:34',
            'label' => 'Parlophone',
            'producer' => 'David Bowie, Tony Visconti'
        ],
        '31' => [
            'id' => 46,
            'artist_name' => 'The Smiths',
            'album_name' => 'The Smiths',
            'release_year' => '1984',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/MEQsW4A2PZkbJRxVrx2V_BK5qS_sd0bcaZDRpCCF-ns/rs:fit/g:sm/q:90/h:938/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTMxMjgy/MzQtMTU1MTIwMzk4/OS05Nzg4LmpwZWc.jpeg',
            'discogs_release_id' => 3128234,
            'style' => 'Indie Rock',
            'format' => 'Cassette,Album',
            'artist_type' => 'Group',
            'created_date' => '2025-08-31 16:04:15',
            'updated_date' => '2025-09-04 01:14:34',
            'label' => 'Sire',
            'producer' => 'John Porter'
        ],
        '33' => [
            'id' => 50,
            'artist_name' => 'The The',
            'album_name' => 'Uncertain Smile',
            'release_year' => '1982',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/TOk5NObL7jaVT0Hm_ra4k7vizVYlk63Amp7s9KnKAIQ/rs:fit/g:sm/q:90/h:600/w:579/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE0OTU3/NC0xMTI0OTgzOTcx/LmpwZw.jpeg',
            'discogs_release_id' => 149574,
            'style' => 'Synth-pop',
            'format' => 'Vinyl,12",45 RPM,Maxi-Single',
            'artist_type' => 'Person',
            'created_date' => '2025-09-03 12:52:48',
            'updated_date' => '2025-09-04 01:14:50',
            'label' => 'Sire',
            'producer' => 'Mike Thorne'
        ],
        '34' => [
            'id' => 51,
            'artist_name' => 'Archers Of Loaf',
            'album_name' => 'White Trash Heroes',
            'release_year' => '1998',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/H9Q-V1hMxNcm_zgP40VYlrLM7PQACw-6cPZaGN51Bbs/rs:fit/g:sm/q:90/h:530/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTg2MDI4/Ny0xNzQ2NTYzNzk0/LTMwODQuanBlZw.jpeg',
            'discogs_release_id' => 860287,
            'style' => 'Indie Rock',
            'format' => 'CD,Album',
            'artist_type' => 'Group',
            'created_date' => '2025-09-03 13:15:14',
            'updated_date' => '2025-09-04 01:12:06',
            'label' => 'Alias',
            'producer' => 'Archers Of Loaf, Brian Paulson'
        ],
        '35' => [
            'id' => 55,
            'artist_name' => 'The The',
            'album_name' => 'We Can\'t Stop What\'s Coming',
            'release_year' => '2017',
            'is_owned' => 1,
            'want_to_own' => 0,
            'cover_url' => 'https://i.discogs.com/Lj4jhgG9kG_S0rbyXP18O_-YzH5bM87zIdFThaEz43Q/rs:fit/g:sm/q:90/h:604/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTEwMTcy/NTkyLTE0OTUwNTA5/MjQtMzkyMS5qcGVn.jpeg',
            'discogs_release_id' => 10172592,
            'style' => 'Alternative Rock',
            'format' => 'Vinyl,7",45 RPM,Single Sided,Record Store Day,Single,Etched,Limited Edition',
            'artist_type' => 'Person',
            'label' => 'Cinéola',
            'producer' => 'Matt Johnson',
            'created_date' => '2025-09-04 21:28:06',
            'updated_date' => '2025-09-04 21:28:06'
        ]
    ],
    'next_id' => 56
];

file_put_contents(__DIR__ . '/data/music_collection.json', json_encode($demoData, JSON_PRETTY_PRINT));

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'Demo reset successfully! Password restored to admin123 and sample data refreshed.'
]);
?>
