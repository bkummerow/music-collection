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
        
        .reset-container {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .reset-header p {
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
        
        .password-status {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #666;
            text-align: center;
        }
        
        .password-status.set {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .password-status.not-set {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn-submit {
            background: #dc3545;
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
            background: #c82333;
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
        
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .warning h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .warning p {
            color: #856404;
            font-size: 0.9rem;
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
        
        .password-requirements {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .password-requirements h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .password-requirements ul {
            margin-left: 20px;
            color: #424242;
            font-size: 0.9rem;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
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
        
        <?php if (isset($_SESSION['password_reset_complete']) && $_SESSION['password_reset_complete']): ?>
            <div style="text-align: center;">
                <a href="index.php" class="btn-home">Go to Music Collection</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
