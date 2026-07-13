<?php
/**
 * WebAuthn / passkey helpers for Face ID, Touch ID, and Windows Hello.
 */

require_once __DIR__ . '/auth_config.php';
require_once __DIR__ . '/../lib/lbuchs-WebAuthn/WebAuthn.php';

/**
 * Single-admin WebAuthn registration and assertion helpers.
 */
class WebAuthnHelper {
    const CREDENTIALS_FILE = __DIR__ . '/webauthn_credentials.json';
    const ADMIN_USER_ID = 'admin001musiccoll';
    const ADMIN_USER_NAME = 'admin';
    const ADMIN_DISPLAY_NAME = 'Site Admin';

    /**
     * Build a WebAuthn server instance for the current host.
     *
     * @return \lbuchs\WebAuthn\WebAuthn
     */
    public static function createServer() {
        $rpId = self::getRpId();
        $formats = array('none', 'packed', 'apple', 'android-key', 'tpm', 'fido-u2f');
        return new \lbuchs\WebAuthn\WebAuthn('Music Collection', $rpId, $formats);
    }

    /**
     * Relying party ID is the hostname without port.
     *
     * @return string
     */
    public static function getRpId() {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $host = preg_replace('/:\d+$/', '', $host);
        return $host !== '' ? $host : 'localhost';
    }

    /**
     * Load stored credential records from disk.
     *
     * @return array
     */
    public static function loadCredentials() {
        if (!file_exists(self::CREDENTIALS_FILE)) {
            return array();
        }

        $raw = file_get_contents(self::CREDENTIALS_FILE);
        if ($raw === false || $raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['credentials']) || !is_array($decoded['credentials'])) {
            return array();
        }

        return $decoded['credentials'];
    }

    /**
     * Persist credential records to disk.
     *
     * @param array $credentials
     * @return bool
     */
    public static function saveCredentials($credentials) {
        $payload = json_encode(array(
            'credentials' => array_values($credentials)
        ), JSON_PRETTY_PRINT);

        if ($payload === false) {
            return false;
        }

        return file_put_contents(self::CREDENTIALS_FILE, $payload) !== false;
    }

    /**
     * Whether any passkeys are registered.
     *
     * @return bool
     */
    public static function hasCredentials() {
        return count(self::loadCredentials()) > 0;
    }

    /**
     * Public status payload for the login UI.
     *
     * @return array
     */
    public static function getStatus() {
        $credentials = self::loadCredentials();
        return array(
            'has_credentials' => count($credentials) > 0,
            'credential_count' => count($credentials)
        );
    }

    /**
     * Create registration options for navigator.credentials.create().
     *
     * @return array
     */
    public static function getRegisterOptions() {
        ensureSessionStarted();

        $webAuthn = self::createServer();
        $excludeIds = array();

        foreach (self::loadCredentials() as $credential) {
            if (!empty($credential['credentialId'])) {
                $excludeIds[] = base64_decode($credential['credentialId']);
            }
        }

        // Prefer platform authenticators (Face ID / Touch ID / Windows Hello).
        $createArgs = $webAuthn->getCreateArgs(
            self::ADMIN_USER_ID,
            self::ADMIN_USER_NAME,
            self::ADMIN_DISPLAY_NAME,
            60 * 4,
            true,
            'required',
            false,
            $excludeIds
        );

        $_SESSION['webauthn_challenge'] = $webAuthn->getChallenge()->getBinaryString();

        return array(
            'success' => true,
            'message' => 'Registration options ready',
            'data' => $createArgs
        );
    }

    /**
     * Verify and store a new passkey registration.
     *
     * @param object|array $clientData
     * @return array
     */
    public static function processRegister($clientData) {
        ensureSessionStarted();

        if (empty($_SESSION['webauthn_challenge'])) {
            return array('success' => false, 'message' => 'Registration challenge expired. Please try again.');
        }

        $clientData = (object) $clientData;
        if (empty($clientData->clientDataJSON) || empty($clientData->attestationObject)) {
            return array('success' => false, 'message' => 'Invalid registration payload.');
        }

        try {
            $webAuthn = self::createServer();
            $createData = $webAuthn->processCreate(
                base64_decode($clientData->clientDataJSON),
                base64_decode($clientData->attestationObject),
                $_SESSION['webauthn_challenge'],
                true,
                true,
                false
            );

            unset($_SESSION['webauthn_challenge']);

            $credentials = self::loadCredentials();
            $credentials[] = array(
                'id' => base64_encode($createData->credentialId),
                'credentialId' => base64_encode($createData->credentialId),
                'publicKey' => $createData->credentialPublicKey,
                'signatureCounter' => isset($createData->signatureCounter) ? (int) $createData->signatureCounter : 0,
                'transports' => !empty($clientData->transports) && is_array($clientData->transports)
                    ? $clientData->transports
                    : array('internal'),
                'createdAt' => date('c'),
                'label' => self::buildCredentialLabel($clientData)
            );

            if (!self::saveCredentials($credentials)) {
                return array('success' => false, 'message' => 'Could not save passkey. Check file permissions.');
            }

            return array(
                'success' => true,
                'message' => 'Face ID / fingerprint login enabled for this device.',
                'data' => self::getStatus()
            );
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Passkey registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Create assertion options for navigator.credentials.get().
     *
     * @return array
     */
    public static function getLoginOptions() {
        ensureSessionStarted();

        $credentials = self::loadCredentials();
        if (count($credentials) === 0) {
            return array('success' => false, 'message' => 'No passkeys are registered yet.');
        }

        $ids = array();
        foreach ($credentials as $credential) {
            if (!empty($credential['credentialId'])) {
                $ids[] = base64_decode($credential['credentialId']);
            }
        }

        $webAuthn = self::createServer();
        $getArgs = $webAuthn->getGetArgs(
            $ids,
            60 * 4,
            false,
            false,
            false,
            true,
            true,
            'required'
        );

        $_SESSION['webauthn_challenge'] = $webAuthn->getChallenge()->getBinaryString();

        return array(
            'success' => true,
            'message' => 'Login options ready',
            'data' => $getArgs
        );
    }

    /**
     * Verify a passkey assertion and establish an authenticated session.
     *
     * @param object|array $clientData
     * @return array
     */
    public static function processLogin($clientData) {
        ensureSessionStarted();

        if (AuthHelper::isLockedOut()) {
            return array('success' => false, 'message' => 'Too many failed attempts. Please try again later.');
        }

        if (empty($_SESSION['webauthn_challenge'])) {
            return array('success' => false, 'message' => 'Login challenge expired. Please try again.');
        }

        $clientData = (object) $clientData;
        if (empty($clientData->id) || empty($clientData->clientDataJSON)
            || empty($clientData->authenticatorData) || empty($clientData->signature)) {
            return array('success' => false, 'message' => 'Invalid passkey login payload.');
        }

        $credentialId = base64_decode($clientData->id);
        $stored = null;
        $storedIndex = null;
        $credentials = self::loadCredentials();

        foreach ($credentials as $index => $credential) {
            if (!empty($credential['credentialId'])
                && base64_decode($credential['credentialId']) === $credentialId) {
                $stored = $credential;
                $storedIndex = $index;
                break;
            }
        }

        if ($stored === null) {
            return array('success' => false, 'message' => 'Passkey not recognized. Register it from Settings after password login.');
        }

        try {
            $webAuthn = self::createServer();
            $webAuthn->processGet(
                base64_decode($clientData->clientDataJSON),
                base64_decode($clientData->authenticatorData),
                base64_decode($clientData->signature),
                $stored['publicKey'],
                $_SESSION['webauthn_challenge'],
                isset($stored['signatureCounter']) ? (int) $stored['signatureCounter'] : null,
                true,
                true
            );

            unset($_SESSION['webauthn_challenge']);

            // Persist updated signature counter when the authenticator provides one.
            $newCounter = null;
            try {
                $authData = new \lbuchs\WebAuthn\Attestation\AuthenticatorData(
                    base64_decode($clientData->authenticatorData)
                );
                $newCounter = $authData->getSignCount();
            } catch (Exception $ignored) {
                $newCounter = null;
            }

            if ($newCounter !== null && $storedIndex !== null) {
                $credentials[$storedIndex]['signatureCounter'] = (int) $newCounter;
                self::saveCredentials($credentials);
            }

            AuthHelper::establishSession();

            return array(
                'success' => true,
                'message' => 'Authentication successful',
                'data' => array('authenticated' => true)
            );
        } catch (Exception $e) {
            $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
            $_SESSION['last_attempt'] = time();
            if ($_SESSION['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION['lockout_time'] = time();
            }

            return array('success' => false, 'message' => 'Passkey login failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove all stored passkeys.
     *
     * @return array
     */
    public static function deleteAllCredentials() {
        if (file_exists(self::CREDENTIALS_FILE) && !unlink(self::CREDENTIALS_FILE)) {
            return array('success' => false, 'message' => 'Could not remove saved passkeys.');
        }

        return array(
            'success' => true,
            'message' => 'All saved Face ID / fingerprint logins were removed.',
            'data' => self::getStatus()
        );
    }

    /**
     * Build a short label for the registered device/browser.
     *
     * @param object $clientData
     * @return string
     */
    private static function buildCredentialLabel($clientData) {
        if (!empty($clientData->label) && is_string($clientData->label)) {
            return substr(trim($clientData->label), 0, 80);
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown device';
        return substr($ua, 0, 80);
    }
}
