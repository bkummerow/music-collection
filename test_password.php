<?php
// Test password hash generation and verification

$testPassword = 'admin123';
$hash = password_hash($testPassword, PASSWORD_DEFAULT);

echo "Generated hash: " . $hash . "\n";
echo "Verification test: " . (password_verify($testPassword, $hash) ? 'SUCCESS' : 'FAILED') . "\n";

// Test with the current hash from the file
$currentHash = '$2y$12$yjRPlXIF1Gn.Yc4Cfx1Kcexz/v0MI7J28Qa8PfSO9BBUiTVny/W1G';
echo "Current hash verification: " . (password_verify($testPassword, $currentHash) ? 'SUCCESS' : 'FAILED') . "\n";
?>
