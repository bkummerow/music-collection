<?php
/**
 * Password Setup Script
 * Use this to generate a new password hash for your music collection app
 */

echo "<h1>🔐 Password Setup</h1>";
echo "<p>Generate a new password hash for your music collection app</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    
    if (!empty($newPassword)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        echo "<h2>✅ Password Hash Generated</h2>";
        echo "<p><strong>Your new password hash:</strong></p>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<code>" . htmlspecialchars($hash) . "</code>";
        echo "</div>";
        
        echo "<h3>📝 Instructions:</h3>";
        echo "<ol>";
        echo "<li>Copy the hash above</li>";
        echo "<li>Open <code>config/auth_config.php</code></li>";
        echo "<li>Replace the <code>ADMIN_PASSWORD_HASH</code> value with your new hash</li>";
        echo "<li>Save the file</li>";
        echo "<li>Delete this setup file for security</li>";
        echo "</ol>";
        
        echo "<p><strong>⚠️ Security Note:</strong> Remember to delete this file after setting your password!</p>";
        
    } else {
        echo "<p style='color: #dc3545;'>Please enter a password.</p>";
    }
} else {
    ?>
    <form method="POST" style="max-width: 500px; margin: 20px 0;">
        <div style="margin-bottom: 15px;">
            <label for="password" style="display: block; margin-bottom: 5px; font-weight: bold;">New Password:</label>
            <input type="password" id="password" name="password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Generate Hash</button>
    </form>
    
    <h3>🔧 Current Default Password:</h3>
    <p>The current default password is: <strong>password</strong></p>
    <p>You should change this to your own secure password.</p>
    
    <h3>💡 Password Tips:</h3>
    <ul>
        <li>Use a strong password with at least 8 characters</li>
        <li>Include uppercase, lowercase, numbers, and symbols</li>
        <li>Don't use common words or phrases</li>
        <li>Consider using a password manager</li>
    </ul>
    
    <h3>🛡️ Security Features:</h3>
    <ul>
        <li><strong>Session Timeout:</strong> 30 minutes of inactivity</li>
        <li><strong>Login Attempts:</strong> 3 attempts before lockout</li>
        <li><strong>Lockout Duration:</strong> 15 minutes</li>
        <li><strong>Password Hashing:</strong> Secure bcrypt hashing</li>
    </ul>
    <?php
}

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 