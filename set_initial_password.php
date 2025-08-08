<?php
/**
 * Set Initial Password
 * Set a known password for testing
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Setting Initial Password</h2>";
echo "<p><strong>Password:</strong> $password</p>";
echo "<p><strong>Hash:</strong> $hash</p>";

// Read current auth config file
$authFile = __DIR__ . '/config/auth_config.php';
$authContent = file_get_contents($authFile);

if ($authContent === false) {
    echo "<p style='color: red;'>Could not read authentication configuration file.</p>";
} else {
    // Replace the password hash in the config
    $newAuthContent = preg_replace(
        "/define\('ADMIN_PASSWORD_HASH',\s*'[^']*'\);/",
        "define('ADMIN_PASSWORD_HASH', '$hash');",
        $authContent
    );
    
    // Write the updated config back to file
    if (file_put_contents($authFile, $newAuthContent) !== false) {
        echo "<p style='color: green;'>Password set successfully!</p>";
        echo "<p>You can now log in with password: <strong>$password</strong></p>";
        echo "<p><a href='index.php'>Go to Music Collection</a></p>";
    } else {
        echo "<p style='color: red;'>Could not write to authentication configuration file.</p>";
    }
}
?>
