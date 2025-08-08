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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="setup-page">
    <div class="setup-container">
        <div class="setup-header">
            <h1>Music Collection Setup</h1>
            <p>Configure your Discogs API key and set up authentication</p>
        </div>
        
        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success) && $success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="setup-instructions">
            <h3>How to get your Discogs API key:</h3>
            <ol>
                <li>Go to <a href="https://www.discogs.com/settings/developers" target="_blank">Discogs Developer Settings</a></li>
                <li>Create a new application</li>
                <li>Copy your Consumer Key (this is your API key)</li>
                <li>Paste it in the field below</li>
            </ol>
        </div>
        
        <form method="POST">
            <div class="setup-form-group">
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
        
        <div class="setup-auth-section">
            <h3>Authentication Setup</h3>
            <p>
                Set up a password to protect your music collection. This password will be required to add, edit, or delete albums.
            </p>
            
            <?php
            // Check if password is set
            $passwordSet = false;
            $authFile = __DIR__ . '/config/auth_config.php';
            if (file_exists($authFile)) {
                $authContent = file_get_contents($authFile);
                if (preg_match("/define\('ADMIN_PASSWORD_HASH',\s*'([^']*)'\);/", $authContent, $matches)) {
                    $passwordSet = !empty($matches[1]) && $matches[1] !== 'YOUR_PASSWORD_HASH_HERE';
                }
            }
            ?>
            
            <?php if ($passwordSet): ?>
                <div class="message success">
                    <strong>✅ Password is set</strong><br>
                    You can change your password using the link below.
                </div>
            <?php else: ?>
                <div class="message error">
                    <strong>⚠️ No password set</strong><br>
                    You need to set a password to protect your music collection.
                </div>
            <?php endif; ?>
            
            <div class="setup-auth-actions">
                <a href="reset_password.php" class="btn-home">
                    <?php echo $passwordSet ? 'Change Password' : 'Set Password'; ?>
                </a>
            </div>
        </div>
        
        <?php
        // Check overall setup status
        $apiKeySet = !empty($currentApiKey) && $currentApiKey !== 'Not set';
        $setupComplete = $apiKeySet && $passwordSet;
        ?>
        
        <?php if ($setupComplete): ?>
            <div class="setup-complete">
                <h3>✅ Setup Complete!</h3>
                <p>
                    Your music collection is ready to use. You can now add, edit, and manage your albums.
                </p>
                <a href="index.php" class="btn-submit">
                    Go to Music Collection
                </a>
            </div>
        <?php elseif (isset($_SESSION['setup_complete']) && $_SESSION['setup_complete']): ?>
            <div class="setup-auth-actions">
                <a href="index.php" class="btn-home">Go to Music Collection</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
