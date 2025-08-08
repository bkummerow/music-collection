<?php
$newPassword = "7.Gynslpj";
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
echo "New hash: " . $hash;
?>