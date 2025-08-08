<?php
/**
 * Setup Configuration
 * Web interface to configure API keys and settings
 */

// Start session for form handling
session_start();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $discogsApiKey = trim($_POST['discogs_api_key'] ?? '');
    $error = '';
    $success = '';
    
    // Validate API key
    if (empty($discogsApiKey)) {
        $error = 'Discogs API key is required.';
    } elseif (strlen($discogsApiKey) < 10) {
        $error = 'Discogs API key appears to be too short. Please check your key.';
    } else {
        // Read current config file
        $configFile = __DIR__ . '/config/api_config.php';
        $configContent = file_get_contents($configFile);
        
        if ($configContent === false) {
            $error = 'Could not read configuration file.';
        } else {
            // Replace the API key in the config
            $newConfigContent = preg_replace(
                "/define\('DISCOGS_API_KEY',\s*'[^']*'\);/",
                "define('DISCOGS_API_KEY', '" . addslashes($discogsApiKey) . "');",
                $configContent
            );
            
            // Write the updated config back to file
            if (file_put_contents($configFile, $newConfigContent) !== false) {
                $success = 'Discogs API key updated successfully!';
                $_SESSION['setup_complete'] = true;
            } else {
                $error = 'Could not write to configuration file. Please check file permissions.';
            }
        }
    }
}

// Get current API key (masked for security)
$currentApiKey = '';
$configFile = __DIR__ . '/config/api_config.php';
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    if (preg_match("/define\('DISCOGS_API_KEY',\s*'([^']*)'\);/", $configContent, $matches)) {
        $currentApiKey = $matches[1];
        // Mask the API key for display (show first 4 and last 4 characters)
        if (strlen($currentApiKey) > 8) {
            $currentApiKey = substr($currentApiKey, 0, 4) . '...' . substr($currentApiKey, -4);
        } else {
            $currentApiKey = 'Not set';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Collection Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .current-value {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .btn-submit {
            background: #28a745;
            color: #fff;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            background: #218838;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .instructions {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .instructions h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .instructions ol {
            margin-left: 20px;
            color: #424242;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .btn-home {
            background: #6c757d;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        
        .btn-home:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>Music Collection Setup</h1>
            <p>Configure your Discogs API key to enable album search and cover art</p>
        </div>
        
        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success) && $success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="instructions">
            <h3>How to get your Discogs API key:</h3>
            <ol>
                <li>Go to <a href="https://www.discogs.com/settings/developers" target="_blank">Discogs Developer Settings</a></li>
                <li>Create a new application</li>
                <li>Copy your Consumer Key (this is your API key)</li>
                <li>Paste it in the field below</li>
            </ol>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="discogs_api_key">Discogs API Key</label>
                <?php if ($currentApiKey && $currentApiKey !== 'Not set'): ?>
                    <div class="current-value">
                        <strong>Current:</strong> <?php echo htmlspecialchars($currentApiKey); ?>
                    </div>
                <?php endif; ?>
                <input 
                    type="text" 
                    id="discogs_api_key" 
                    name="discogs_api_key" 
                    placeholder="Enter your Discogs API key"
                    value="<?php echo htmlspecialchars($_POST['discogs_api_key'] ?? ''); ?>"
                    required
                >
            </div>
            
            <button type="submit" class="btn-submit">Save Configuration</button>
        </form>
        
        <?php if (isset($_SESSION['setup_complete']) && $_SESSION['setup_complete']): ?>
            <div style="text-align: center;">
                <a href="index.php" class="btn-home">Go to Music Collection</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
