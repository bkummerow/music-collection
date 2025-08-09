<?php
/**
 * Reset Password Script
 * Web interface to change the admin password
 */

// Start session for form handling
session_start();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $error = '';
    $success = '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        require_once __DIR__ . '/config/auth_config.php';
        
        if (password_verify($currentPassword, ADMIN_PASSWORD_HASH)) {
            // Hash the new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Read current auth config file
            $authFile = __DIR__ . '/config/auth_config.php';
            $authContent = file_get_contents($authFile);
            
            if ($authContent === false) {
                $error = 'Could not read authentication configuration file.';
            } else {
                // Replace the password hash in the config - use simpler approach
                $lines = explode("\n", $authContent);
                $newLines = [];
                $found = false;
                
                foreach ($lines as $line) {
                    if (strpos($line, "define('ADMIN_PASSWORD_HASH'") !== false) {
                        $newLines[] = "define('ADMIN_PASSWORD_HASH', '" . addslashes($newPasswordHash) . "');";
                        $found = true;
                    } else {
                        $newLines[] = $line;
                    }
                }
                
                if ($found) {
                    $newAuthContent = implode("\n", $newLines);
                } else {
                    $error = 'Could not find ADMIN_PASSWORD_HASH in configuration file.';
                }
                
                // Write the updated config back to file
                if (empty($error) && file_put_contents($authFile, $newAuthContent) !== false) {
                    $success = 'Password updated successfully! You can now log in with your new password.';
                    $_SESSION['password_reset_complete'] = true;
                } else {
                    $error = 'Could not write to authentication configuration file. Please check file permissions.';
                }
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Music Collection</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="reset-password-page">
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Admin Password</h1>
            <p>Change the password for your Music Collection app</p>
        </div>
        
        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success) && $success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="password-status <?php echo $passwordSet ? 'set' : 'not-set'; ?>">
            <strong>Password Status:</strong> 
            <?php echo $passwordSet ? 'Set' : 'Not set'; ?>
        </div>
        
        <div class="warning">
            <h3>⚠️ Security Warning</h3>
            <p>This will change the admin password for your Music Collection app.</p>
            <p>Make sure to remember your new password - there's no password recovery option.</p>
        </div>
        
        <div class="password-requirements">
            <h3>Password Requirements:</h3>
            <ul>
                <li>At least 6 characters long</li>
                <li>Use a strong, unique password</li>
                <li>Consider using a password manager</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    placeholder="Enter current password"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    placeholder="Enter new password"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    placeholder="Confirm new password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-submit">Update Password</button>
        </form>

        <div class="back-to-app">
            <a href="index.php" class="btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Back to Music Collection
            </a>
        </div>

    </div>
</body>
</html>
