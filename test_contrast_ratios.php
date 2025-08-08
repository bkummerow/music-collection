<?php
/**
 * Test Contrast Ratios
 * Verifies that color combinations meet WCAG accessibility standards
 */

echo "<h1>🎨 Test Contrast Ratios</h1>";
echo "<p>Verifying that color combinations meet WCAG accessibility standards</p>";

// WCAG contrast ratio requirements
$wcag_aa_normal = 4.5; // Normal text
$wcag_aa_large = 3.0;  // Large text (18pt+ or 14pt+ bold)
$wcag_aaa_normal = 7.0; // AAA normal text
$wcag_aaa_large = 4.5;  // AAA large text

function calculateContrastRatio($color1, $color2) {
    // Convert hex to RGB
    $rgb1 = hex2rgb($color1);
    $rgb2 = hex2rgb($color2);
    
    // Calculate relative luminance
    $luminance1 = calculateLuminance($rgb1);
    $luminance2 = calculateLuminance($rgb2);
    
    // Calculate contrast ratio
    $lighter = max($luminance1, $luminance2);
    $darker = min($luminance1, $luminance2);
    
    return ($lighter + 0.05) / ($darker + 0.05);
}

function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function calculateLuminance($rgb) {
    $r = $rgb[0] / 255;
    $g = $rgb[1] / 255;
    $b = $rgb[2] / 255;
    
    $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
    
    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

// Test color combinations
$testCombinations = [
    [
        'name' => 'Album Name Text',
        'foreground' => '#495057',
        'background' => '#ffffff',
        'type' => 'normal'
    ],
    [
        'name' => 'Album Link Hover',
        'foreground' => '#0056b3',
        'background' => '#ffffff',
        'type' => 'normal'
    ],
    [
        'name' => 'Year Badge',
        'foreground' => '#ffffff',
        'background' => '#6c757d',
        'type' => 'normal'
    ],
    [
        'name' => 'Checkmark',
        'foreground' => '#155724',
        'background' => '#ffffff',
        'type' => 'large'
    ],
    [
        'name' => 'Form Labels',
        'foreground' => '#2c3e50',
        'background' => '#ffffff',
        'type' => 'normal'
    ],
    [
        'name' => 'Form Input Text',
        'foreground' => '#2c3e50',
        'background' => '#ffffff',
        'type' => 'normal'
    ],
    [
        'name' => 'Disabled Input',
        'foreground' => '#495057',
        'background' => '#e9ecef',
        'type' => 'normal'
    ],
    [
        'name' => 'Success Message',
        'foreground' => '#155724',
        'background' => '#d4edda',
        'type' => 'normal'
    ],
    [
        'name' => 'Error Message',
        'foreground' => '#721c24',
        'background' => '#f8d7da',
        'type' => 'normal'
    ],
    [
        'name' => 'Track Title',
        'foreground' => '#2c3e50',
        'background' => '#ffffff',
        'type' => 'normal'
    ],
    [
        'name' => 'No Cover Placeholder',
        'foreground' => '#495057',
        'background' => '#e9ecef',
        'type' => 'normal'
    ],
    [
        'name' => 'Filter Button (Default)',
        'foreground' => '#495057',
        'background' => '#e9ecef',
        'type' => 'normal'
    ],
    [
        'name' => 'Filter Button (Active)',
        'foreground' => '#ffffff',
        'background' => '#0056b3',
        'type' => 'normal'
    ],
    [
        'name' => 'Add Album Button',
        'foreground' => '#ffffff',
        'background' => '#155724',
        'type' => 'normal'
    ],
    [
        'name' => 'Edit Button',
        'foreground' => '#ffffff',
        'background' => '#0056b3',
        'type' => 'normal'
    ],
    [
        'name' => 'Delete Button',
        'foreground' => '#ffffff',
        'background' => '#dc3545',
        'type' => 'normal'
    ]
];

echo "<h2>📊 Contrast Ratio Analysis</h2>";

echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Element</th>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Foreground</th>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Background</th>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Contrast Ratio</th>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>WCAG AA</th>";
echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>WCAG AAA</th>";
echo "</tr>";

foreach ($testCombinations as $test) {
    $ratio = calculateContrastRatio($test['foreground'], $test['background']);
    $required_aa = $test['type'] === 'large' ? $wcag_aa_large : $wcag_aa_normal;
    $required_aaa = $test['type'] === 'large' ? $wcag_aaa_large : $wcag_aaa_normal;
    
    $aa_status = $ratio >= $required_aa ? '✅ Pass' : '❌ Fail';
    $aaa_status = $ratio >= $required_aaa ? '✅ Pass' : '❌ Fail';
    
    $row_color = $ratio >= $required_aa ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: {$row_color};'>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($test['name']) . "</td>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($test['foreground']) . "</td>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($test['background']) . "</td>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>" . number_format($ratio, 2) . ":1</td>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$aa_status}</td>";
    echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$aaa_status}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🎯 WCAG Standards</h2>";

echo "<h3>WCAG AA (Minimum):</h3>";
echo "<ul>";
echo "<li><strong>Normal Text:</strong> 4.5:1 contrast ratio</li>";
echo "<li><strong>Large Text:</strong> 3.0:1 contrast ratio (18pt+ or 14pt+ bold)</li>";
echo "</ul>";

echo "<h3>WCAG AAA (Enhanced):</h3>";
echo "<ul>";
echo "<li><strong>Normal Text:</strong> 7.0:1 contrast ratio</li>";
echo "<li><strong>Large Text:</strong> 4.5:1 contrast ratio</li>";
echo "</ul>";

echo "<h2>✅ Improvements Made</h2>";
echo "<ul>";
echo "<li><strong>Album Text:</strong> Changed from #6c757d to #495057 for better contrast</li>";
echo "<li><strong>Links:</strong> Updated hover colors to #0056b3 for better visibility</li>";
echo "<li><strong>Year Badge:</strong> Dark background (#6c757d) with white text</li>";
echo "<li><strong>Form Elements:</strong> Darker text colors for better readability</li>";
echo "<li><strong>Messages:</strong> Improved contrast for success/error states</li>";
echo "<li><strong>Placeholders:</strong> Better contrast for 'No Cover' elements</li>";
echo "</ul>";

echo "<h2>🔍 Lighthouse Impact</h2>";
echo "<ul>";
echo "<li><strong>Accessibility Score:</strong> Should improve significantly</li>";
echo "<li><strong>Contrast Issues:</strong> All major contrast issues resolved</li>";
echo "<li><strong>User Experience:</strong> Better readability for all users</li>";
echo "<li><strong>Compliance:</strong> Meets WCAG AA standards</li>";
echo "</ul>";

echo "<h2>📱 Mobile Considerations</h2>";
echo "<ul>";
echo "<li><strong>Touch Targets:</strong> Maintained good contrast for mobile buttons</li>";
echo "<li><strong>Readability:</strong> Improved text contrast on small screens</li>";
echo "<li><strong>Accessibility:</strong> Better support for screen readers</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 