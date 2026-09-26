<?php
/**
 * Authentication Configuration
 * Password protection for add and edit functions
 */

/**
 * Centralized session management
 * Ensures all session starts use the same configuration
 */
function ensureSessionStarted() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = AuthHelper::isHttpsRequest();
        session_set_cookie_params([
            'lifetime' => 10800, // 3 hours
            'path' => '/',
            'domain' => '', // Empty domain means cookie is only for exact hostname
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        AuthHelper::ensureCsrfToken();
    }
}

// Password hash - you should change this to your own password
// Use password_hash() to generate a new hash
// Run `php -r "echo password_hash('new_password_here', PASSWORD_DEFAULT);"` to generate a new hash
// You may need to trim the trailing space and/or % sign
define('ADMIN_PASSWORD_HASH', '$2y$10$P5FyMqZxvQZcVSq0TMn6MOs.Z6XNnH5PP3QeJfu1UTMX4t22mf48O');

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
        // Ensure session is started with proper configuration
        ensureSessionStarted();
        
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
     * Seconds until the current login expires.
     *
     * Zero when there is no active login. Call after isAuthenticated(), which
     * clears an already-expired session.
     *
     * @return int
     */
    public static function getSessionSecondsRemaining() {
        ensureSessionStarted();
        if (empty($_SESSION['authenticated']) || empty($_SESSION['auth_time'])) {
            return 0;
        }

        $remaining = SESSION_TIMEOUT - (time() - (int) $_SESSION['auth_time']);
        return $remaining > 0 ? (int) $remaining : 0;
    }
    
    /**
     * Authenticate user with password
     */
    public static function authenticate($password) {
        // Ensure session is started with proper configuration
        ensureSessionStarted();
        
        // Check for lockout
        if (self::isLockedOut()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        
        // Verify password
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            self::establishSession();
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
     * Mark the current session as authenticated (password or WebAuthn).
     */
    public static function establishSession() {
        ensureSessionStarted();
        session_regenerate_id(true);

        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();

        if (isset($_SESSION['failed_attempts'])) {
            unset($_SESSION['failed_attempts']);
            unset($_SESSION['lockout_time']);
        }

        self::markMustChangePasswordIfNeeded();
    }
    
    /**
     * Check if account is locked out
     */
    public static function isLockedOut() {
        // Ensure session is started with proper configuration
        ensureSessionStarted();
        
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
        // Ensure session is started with proper configuration
        ensureSessionStarted();
        
        if (!isset($_SESSION['lockout_time'])) {
            return 0;
        }
        
        $remaining = LOCKOUT_DURATION - (time() - $_SESSION['lockout_time']);
        return max(0, $remaining);
    }

    /**
     * Whether the current request is served over HTTPS (direct or proxied).
     */
    public static function isHttpsRequest() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }

    /**
     * Whether the app is running in demo mode (DEMO_MODE=true in environment).
     */
    public static function isDemoMode() {
        return isset($_ENV['DEMO_MODE']) && $_ENV['DEMO_MODE'] === 'true';
    }

    /**
     * Send a JSON error response and terminate the request.
     *
     * @param int    $httpCode HTTP status code.
     * @param string $message  User-facing message.
     * @param array  $extra    Additional JSON fields.
     */
    public static function jsonExit($httpCode, $message, $extra = []) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra));
        exit;
    }

    /**
     * Ensure a CSRF token exists in the session and return it.
     */
    public static function ensureCsrfToken() {
        ensureSessionStarted();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Return the current session CSRF token (creating one if needed).
     */
    public static function getCsrfToken() {
        return self::ensureCsrfToken();
    }

    /**
     * Validate a CSRF token against the session value.
     *
     * @param mixed $token Token from header or body.
     */
    public static function validateCsrfToken($token) {
        ensureSessionStarted();
        if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Read CSRF token from X-CSRF-Token header or POST field.
     *
     * Note: php://input can only be read once; prefer header in JS.
     */
    public static function getRequestCsrfToken() {
        $header = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        if (is_string($header) && $header !== '') {
            return $header;
        }
        if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }
        return '';
    }

    /**
     * Require a valid CSRF token or exit with 403 JSON.
     */
    public static function requireCsrf() {
        $token = '';
        if (!empty($GLOBALS['__request_csrf'])) {
            $token = $GLOBALS['__request_csrf'];
        } else {
            $token = self::getRequestCsrfToken();
        }
        if (!self::validateCsrfToken($token)) {
            self::jsonExit(403, 'Invalid CSRF token');
        }
    }

    /**
     * Require an authenticated session or exit with 401 JSON.
     */
    public static function requireAuthenticated() {
        if (!self::isAuthenticated()) {
            self::jsonExit(401, 'Authentication required', ['auth_required' => true]);
        }
    }

    /**
     * Whether the stored admin password is still the default (admin123).
     */
    public static function isDefaultPasswordInUse() {
        return password_verify('admin123', ADMIN_PASSWORD_HASH);
    }

    /**
     * Set must-change-password flag when default password is in use (non-demo).
     */
    public static function markMustChangePasswordIfNeeded() {
        ensureSessionStarted();
        if (!self::isDemoMode() && self::isDefaultPasswordInUse()) {
            $_SESSION['must_change_password'] = true;
        } else {
            unset($_SESSION['must_change_password']);
        }
    }

    /**
     * Whether the current session must change password before admin actions.
     */
    public static function mustChangePassword() {
        ensureSessionStarted();
        return !empty($_SESSION['must_change_password']);
    }

    /**
     * Clear the must-change-password flag after a successful password update.
     */
    public static function clearMustChangePassword() {
        ensureSessionStarted();
        unset($_SESSION['must_change_password']);
    }

    /**
     * Require auth, CSRF, and that password change is not pending.
     */
    public static function requireAdminAction() {
        self::requireAuthenticated();
        self::requireCsrf();
        if (self::mustChangePassword()) {
            self::jsonExit(403, 'Password change required', ['must_change_password' => true]);
        }
    }
}