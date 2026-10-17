<?php
/**
 * Legacy setup URL — opens Setup & Configuration in the collection slide-out panel.
 */

require_once __DIR__ . '/config/auth_config.php';
require_once __DIR__ . '/components/components.php';

ensureSessionStarted();

if (!AuthHelper::isAuthenticated()) {
    header('Location: ' . getCollectionHomeHref('error=setup_requires_auth'));
    exit();
}

header('Location: ' . getCollectionHomeHref('setup=1'));
exit();
