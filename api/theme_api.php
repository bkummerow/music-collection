<?php
/**
 * Theme API - Handle theme color saving and loading
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$themeFile = __DIR__ . '/../data/theme.json';
$displayModeFile = __DIR__ . '/../data/display_mode.json';
$defaultColors = [
    'gradient_color_1' => '#667eea',
    'gradient_color_2' => '#764ba2'
];
$defaultDisplayMode = 'light';

function loadThemeColors() {
    global $themeFile, $defaultColors;
    
    if (file_exists($themeFile)) {
        $content = file_get_contents($themeFile);
        $theme = json_decode($content, true);
        
        if ($theme && is_array($theme)) {
            return array_merge($defaultColors, $theme);
        }
    }
    
    return $defaultColors;
}

function saveThemeColors($colors) {
    global $themeFile;
    
    // Validate colors
    if (!isset($colors['gradient_color_1']) || !isset($colors['gradient_color_2'])) {
        return ['success' => false, 'message' => 'Missing color parameters'];
    }
    
    // Validate hex format
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colors['gradient_color_1']) ||
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $colors['gradient_color_2'])) {
        return ['success' => false, 'message' => 'Invalid color format'];
    }
    
    // Create theme data
    $themeData = [
        'gradient_color_1' => $colors['gradient_color_1'],
        'gradient_color_2' => $colors['gradient_color_2']
    ];
    
    // Clear file cache before writing
    clearstatcache(true, $themeFile);
    
    // Save to file
    $result = file_put_contents($themeFile, json_encode($themeData, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to save theme colors'];
    }
    
    // Verify the file was written correctly
    $writtenContent = file_get_contents($themeFile);
    $writtenData = json_decode($writtenContent, true);
    
    if ($writtenData !== $themeData) {
        return ['success' => false, 'message' => 'Theme colors were not saved correctly'];
    }
    
    return ['success' => true, 'message' => 'Theme colors saved successfully'];
}

function loadDisplayMode() {
    global $displayModeFile, $defaultDisplayMode;
    
    if (file_exists($displayModeFile)) {
        $content = file_get_contents($displayModeFile);
        $displayModeData = json_decode($content, true);
        
        if ($displayModeData && is_array($displayModeData) && isset($displayModeData['theme'])) {
            return $displayModeData['theme'];
        }
    }
    
    return $defaultDisplayMode;
}

function saveDisplayMode($theme) {
    global $displayModeFile;
    
    // Validate theme
    if (!in_array($theme, ['light', 'dark'])) {
        return ['success' => false, 'message' => 'Invalid display mode'];
    }
    
    $displayModeData = ['theme' => $theme];
    
    // Clear file cache before writing
    clearstatcache(true, $displayModeFile);
    
    // Save to file
    $result = file_put_contents($displayModeFile, json_encode($displayModeData, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to save display mode'];
    }
    
    // Verify the file was written correctly
    $writtenContent = file_get_contents($displayModeFile);
    $writtenData = json_decode($writtenContent, true);
    
    if ($writtenData !== $displayModeData) {
        return ['success' => false, 'message' => 'Display mode was not saved correctly'];
    }
    
    return ['success' => true, 'message' => 'Display mode saved successfully'];
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

// Check if this is a display mode request
$isDisplayModeRequest = isset($_GET['type']) && $_GET['type'] === 'display_mode';

switch ($method) {
    case 'GET':
        if ($isDisplayModeRequest) {
            $displayMode = loadDisplayMode();
            echo json_encode([
                'success' => true,
                'data' => ['theme' => $displayMode]
            ]);
        } else {
            $colors = loadThemeColors();
            echo json_encode([
                'success' => true,
                'data' => $colors
            ]);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data'
            ]);
            break;
        }
        
        if ($isDisplayModeRequest) {
            $result = saveDisplayMode($input['theme'] ?? '');
            echo json_encode($result);
        } else {
            $result = saveThemeColors($input);
            echo json_encode($result);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}
?>
