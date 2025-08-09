<?php
/**
 * Authentication Configuration
 * Password protection for add and edit functions
 */

// Password hash - you should change this to your own password
// Use password_hash() to generate a new hash
// Run `php -r "echo password_hash('new_password_here', PASSWORD_DEFAULT);"` to generate a new hash
// You may need to trim the trailing space and/or % sign
define('ADMIN_PASSWORD_HASH', '$2y$12$Dd/tFf0dW.KWqzfP1PgM5O6A/WCCvPnHTaUcSw0wv7mIlxEQLGih6');

// Session timeout (in seconds) - 3 hours
define('SESSION_TIMEOUT', 10800);

// Maximum login attempts
define('MAX_LOGIN_ATTEMPTS', 5);

// Lockout duration (in seconds) - 15 minutes
define('LOCKOUT_DURATION', 900);

/**
 * Authentication Helper Functions
 */
class AuthHelper {
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        if (!isset($_SESSION['auth_time']) || !isset($_SESSION['authenticated'])) {
            return false;
        }
        
        // Check if session has expired
        if (time() - $_SESSION['auth_time'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        return $_SESSION['authenticated'] === true;
    }
    
    /**
     * Authenticate user with password
     */
    public static function authenticate($password) {
        // Check for lockout
        if (self::isLockedOut()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        
        // Verify password
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['auth_time'] = time();
            
            // Reset failed attempts
            if (isset($_SESSION['failed_attempts'])) {
                unset($_SESSION['failed_attempts']);
                unset($_SESSION['lockout_time']);
            }
            
            return ['success' => true, 'message' => 'Authentication successful'];
        } else {
            // Increment failed attempts
            $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
            $_SESSION['last_attempt'] = time();
            
            // Check if we should lock out
            if ($_SESSION['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION['lockout_time'] = time();
            }
            
            return ['success' => false, 'message' => 'Invalid password'];
        }
    }
    
    /**
     * Check if account is locked out
     */
    public static function isLockedOut() {
        if (!isset($_SESSION['lockout_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['lockout_time'] < LOCKOUT_DURATION) {
            return true;
        }
        
        // Clear lockout if time has passed
        unset($_SESSION['lockout_time']);
        unset($_SESSION['failed_attempts']);
        return false;
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        unset($_SESSION['authenticated']);
        unset($_SESSION['auth_time']);
    }
    
    /**
     * Get remaining lockout time
     */
    public static function getLockoutTimeRemaining() {
        if (!isset($_SESSION['lockout_time'])) {
            return 0;
        }
        
        $remaining = LOCKOUT_DURATION - (time() - $_SESSION['lockout_time']);
        return max(0, $remaining);
    }
}
?> 