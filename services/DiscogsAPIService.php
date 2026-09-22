<?php
/**
 * Discogs API Service
 * Handles album searches and cover art retrieval from Discogs
 */

require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/ImageOptimizationService.php';
require_once __DIR__ . '/AlbumPersonalFields.php';

class DiscogsAPIService {
    private $apiKey;
    private $userAgent;
    private $baseUrl = 'https://api.discogs.com';
    private $preferredCurrency;
    private static $lastRequestTime = 0;
    private static $requestDelay = 1000000; // 1 second in microseconds
    private static $cache = [];
    private static $cacheExpiry = 3600; // 1 hour cache cache
    /** @var array<string, array{media:int,sleeve:int,notes:int}> */
    private $collectionPersonalFieldIdsByUser = [];
    
    public function __construct() {
        $this->apiKey = DISCOGS_API_KEY;
        $this->userAgent = DISCOGS_USER_AGENT;
        $this->preferredCurrency = defined('DISCOGS_CURRENCY') ? DISCOGS_CURRENCY : 'USD';
    }
    
    /**
     * Check if Discogs API is available
     */
    public function isAvailable() {
        return !empty($this->apiKey) && $this->apiKey !== 'YOUR_DISCOGS_API_KEY_HERE';
    }

    /**
     * Return the Discogs username for the configured personal access token.
     *
     * Export writes only work for this username (token holder).
     *
     * @return string|null Username, or null when unavailable
     */
    public function getAuthenticatedUsername() {
        $identity = $this->getTokenIdentity();
        return $identity['username'];
    }

    /**
     * Resolve the Discogs account for the configured API credential.
     *
     * Consumer keys fail /oauth/identity; personal access tokens return a username.
     *
     * @return array{username:?string,error:?string}
     */
    public function getTokenIdentity() {
        if (!$this->isAvailable()) {
            return [
                'username' => null,
                'error' => 'Discogs API key is not configured.',
            ];
        }

        $httpCode = 0;
        $decoded = null;
        try {
            $decoded = $this->makeRequestWithStatus(
                $this->baseUrl . '/oauth/identity',
                ['token' => $this->apiKey],
                $httpCode
            );
        } catch (Exception $e) {
            return [
                'username' => null,
                'error' => 'Could not reach Discogs to verify the API credential: ' . $e->getMessage(),
            ];
        }

        if ($httpCode === 200 && is_array($decoded) && !empty($decoded['username']) && is_string($decoded['username'])) {
            $username = trim($decoded['username']);
            if ($username !== '') {
                return [
                    'username' => $username,
                    'error' => null,
                ];
            }
        }

        $apiMessage = null;
        if (is_array($decoded) && !empty($decoded['message']) && is_string($decoded['message'])) {
            $apiMessage = trim($decoded['message']);
        }

        if ($httpCode === 401 || ($apiMessage && stripos($apiMessage, 'consumer token') !== false)) {
            return [
                'username' => null,
                'error' => 'The saved value looks like a Discogs Consumer Key, not a personal access token. '
                    . 'On Discogs Developer Settings, create/generate a Personal Access Token (user token) '
                    . 'for the account you want to export to, then paste that token in API Config.',
            ];
        }

        if ($apiMessage) {
            return [
                'username' => null,
                'error' => $apiMessage . ' (HTTP ' . $httpCode . ')',
            ];
        }

        return [
            'username' => null,
            'error' => 'Could not verify Discogs personal access token (HTTP ' . $httpCode . ').',
        ];
    }
    
    public function setPreferredCurrency($currency) {
        $currency = strtoupper(trim($currency));
        if ($currency) {
            $this->preferredCurrency = $currency;
        }
    }
    
    /**
     * Clean artist name by removing Discogs identifiers
     */
    private function cleanArtistName($artistName) {
        // Remove Discogs identifiers like (8), (2), etc.
        return preg_replace('/\s*\(\d+\)\s*$/', '', $artistName);
    }
    
    /**
     * Search for releases by barcode (EAN, UPC).
     * Discogs database search accepts barcode in the query; type=release returns matching releases.
     *
     * @param string $barcode Barcode (digits only or as-is)
     * @param int $limit Max number of results
     * @return array List of release arrays with id, title, artist, year, cover_url
     */
    public function searchByBarcode($barcode, $limit = 10) {
        if (!$this->isAvailable()) {
            return [];
        }
        $barcode = trim($barcode);
        if ($barcode === '') {
            return [];
        }
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $barcode,
            'type' => 'release',
            'per_page' => $limit,
            'token' => $this->apiKey
        ];
        $response = $this->makeRequest($url, $params);
        if (!$response || !isset($response['results']) || !is_array($response['results'])) {
            return [];
        }
        $results = [];
        foreach ($response['results'] as $release) {
            $title = $release['title'] ?? '';
            $artist = $release['artist'] ?? '';
            $art = $this->extractCoverArtFromRelease($release);
            $results[] = [
                'id' => $release['id'],
                'title' => $title,
                'artist' => $artist,
                'year' => $release['year'] ?? null,
                'cover_url' => $art['cover_url'],
                'cover_images' => $art['cover_images'],
                'type' => 'release'
            ];
        }
        return $results;
    }

    /**
     * Get full release info by barcode (first matching release).
     * Returns the same structure as getReleaseInfo() plus release_id.
     *
     * @param string $barcode Barcode
     * @return array|null Release info with release_id, or null if not found
     */
    public function getReleaseInfoByBarcode($barcode) {
        $releases = $this->searchByBarcode($barcode, 1);
        if (empty($releases)) {
            return null;
        }
        $releaseId = $releases[0]['id'];
        $info = $this->getReleaseInfo($releaseId);
        if ($info) {
            $info['release_id'] = $releaseId;
        }
        return $info;
    }

    /**
     * Fetch one page of a user's Discogs collection (folder 0 = All).
     *
     * @param string $username Discogs username
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array{releases: array, pagination: array{page:int,pages:int,items:int}}
     * @throws Exception When API key is not configured
     */
    public function getCollectionPage($username, $page = 1, $perPage = 50) {
        if (!$this->isAvailable()) {
            throw new Exception('Discogs API is not available');
        }

        $username = rawurlencode(trim($username));
        if ($username === '') {
            throw new Exception('Discogs username is required');
        }

        $url = $this->baseUrl . "/users/{$username}/collection/folders/0/releases";
        $params = [
            'page' => max(1, (int) $page),
            'per_page' => max(1, min(100, (int) $perPage)),
            'token' => $this->apiKey,
        ];

        $response = $this->makeRequest($url, $params);
        $releases = [];

        if (is_array($response) && !empty($response['releases']) && is_array($response['releases'])) {
            foreach ($response['releases'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $mapped = $this->mapCollectionOrWantItem($item);
                if ($mapped !== null) {
                    $releases[] = $mapped;
                }
            }
        }

        return [
            'releases' => $releases,
            'pagination' => $this->extractImportPagination($response, $page),
        ];
    }

    /**
     * Fetch one page of a user's Discogs wantlist.
     *
     * @param string $username Discogs username
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array{releases: array, pagination: array{page:int,pages:int,items:int}}
     * @throws Exception When API key is not configured
     */
    public function getWantlistPage($username, $page = 1, $perPage = 50) {
        if (!$this->isAvailable()) {
            throw new Exception('Discogs API is not available');
        }

        $username = rawurlencode(trim($username));
        if ($username === '') {
            throw new Exception('Discogs username is required');
        }

        $url = $this->baseUrl . "/users/{$username}/wants";
        $params = [
            'page' => max(1, (int) $page),
            'per_page' => max(1, min(100, (int) $perPage)),
            'token' => $this->apiKey,
        ];

        $response = $this->makeRequest($url, $params);
        $releases = [];

        if (is_array($response) && !empty($response['wants']) && is_array($response['wants'])) {
            foreach ($response['wants'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $mapped = $this->mapCollectionOrWantItem($item);
                if ($mapped !== null) {
                    $releases[] = $mapped;
                }
            }
        }

        return [
            'releases' => $releases,
            'pagination' => $this->extractImportPagination($response, $page),
        ];
    }

    /**
     * Add a release to the user's Discogs collection (folder 0).
     *
     * @param string $username Discogs username
     * @param int|string $releaseId Discogs release ID
     * @return array{status:string,message:?string,http_code:int}
     * @throws Exception When API is unavailable or username/release ID is invalid
     */
    public function addReleaseToCollection($username, $releaseId) {
        return $this->addReleaseWrite('collection', $username, $releaseId);
    }

    /**
     * Add a release to the user's Discogs wantlist.
     *
     * @param string $username Discogs username
     * @param int|string $releaseId Discogs release ID
     * @return array{status:string,message:?string,http_code:int}
     * @throws Exception When API is unavailable or username/release ID is invalid
     */
    public function addReleaseToWantlist($username, $releaseId) {
        return $this->addReleaseWrite('wantlist', $username, $releaseId);
    }

    /**
     * Update media/sleeve grades and notes on a collection instance.
     *
     * Discogs stores these as collection custom fields (typically field ids 1–3),
     * written via POST .../instances/{instance_id}/fields/{field_id} with {"value":"..."}.
     *
     * @param string $username Discogs username
     * @param int|string $folderId Collection folder id
     * @param int|string $releaseId Discogs release ID
     * @param int|string $instanceId Collection instance id
     * @param array $fields Keys: media_condition, sleeve_condition, notes
     * @return array{status:string,message:?string,http_code:int}
     * @throws Exception When API is unavailable or ids are invalid
     */
    public function updateCollectionInstanceFields($username, $folderId, $releaseId, $instanceId, array $fields) {
        if (!$this->isAvailable()) {
            throw new Exception('Discogs API is not available');
        }

        $username = trim($username);
        if ($username === '') {
            throw new Exception('Discogs username is required');
        }

        $folderId = (int) $folderId;
        $releaseId = (int) $releaseId;
        $instanceId = (int) $instanceId;
        if ($folderId < 0 || $releaseId <= 0 || $instanceId <= 0) {
            throw new Exception('Discogs collection folder, release, and instance IDs are required');
        }

        $values = [
            'media' => AlbumPersonalFields::sanitizeGradeFromDiscogs(
                isset($fields['media_condition']) ? $fields['media_condition'] : ''
            ),
            'sleeve' => AlbumPersonalFields::sanitizeGradeFromDiscogs(
                isset($fields['sleeve_condition']) ? $fields['sleeve_condition'] : ''
            ),
            'notes' => isset($fields['notes']) ? trim((string) $fields['notes']) : '',
        ];

        $fieldIds = $this->getCollectionPersonalFieldIds($username);
        $params = [
            'token' => $this->apiKey,
        ];
        $worstStatus = 'updated';
        $worstMessage = null;
        $worstHttpCode = 204;
        $wroteAny = false;

        // Notes first so a later grade rate-limit/error does not skip the notes write.
        foreach (['notes', 'media', 'sleeve'] as $key) {
            $fieldId = isset($fieldIds[$key]) ? (int) $fieldIds[$key] : 0;
            if ($fieldId < 1) {
                continue;
            }

            // Discogs dropdown fields reject empty values (422). Notes textarea accepts "".
            if (($key === 'media' || $key === 'sleeve') && $values[$key] === '') {
                continue;
            }

            $url = $this->baseUrl . '/users/' . rawurlencode($username)
                . '/collection/folders/' . $folderId
                . '/releases/' . $releaseId
                . '/instances/' . $instanceId
                . '/fields/' . $fieldId;

            $httpCode = 0;
            $decoded = $this->makeWriteRequest('POST', $url, $params, $httpCode, [
                'value' => $values[$key],
            ]);
            $mapped = $this->mapFieldWriteResponse($decoded, $httpCode);
            $wroteAny = true;

            if ($mapped['status'] === 'error') {
                $worstStatus = 'error';
                $worstMessage = isset($mapped['message']) ? $mapped['message'] : $worstMessage;
                $worstHttpCode = $httpCode;
            } elseif ($mapped['status'] === 'skipped' && $worstStatus === 'updated') {
                $worstStatus = 'skipped';
                $worstMessage = isset($mapped['message']) ? $mapped['message'] : $worstMessage;
                $worstHttpCode = $httpCode;
            } elseif ($worstStatus === 'updated') {
                $worstHttpCode = $httpCode;
            }
        }

        if (!$wroteAny) {
            return [
                'status' => 'updated',
                'message' => null,
                'http_code' => 204,
            ];
        }

        return [
            'status' => $worstStatus,
            'message' => $worstMessage,
            'http_code' => $worstHttpCode,
        ];
    }

    /**
     * Resolve Discogs collection custom field ids for media, sleeve, and notes.
     *
     * @param string $username Discogs username
     * @return array{media:int,sleeve:int,notes:int}
     */
    public function getCollectionPersonalFieldIds($username) {
        $username = trim($username);
        if ($username !== '' && isset($this->collectionPersonalFieldIdsByUser[$username])) {
            return $this->collectionPersonalFieldIdsByUser[$username];
        }

        // Discogs defaults when the account still uses stock field names/ids.
        $resolved = [
            'media' => 1,
            'sleeve' => 2,
            'notes' => 3,
        ];

        if ($username === '' || !$this->isAvailable()) {
            return $resolved;
        }

        try {
            $url = $this->baseUrl . '/users/' . rawurlencode($username) . '/collection/fields';
            $response = $this->makeRequest($url, [
                'token' => $this->apiKey,
            ]);
            $list = isset($response['fields']) && is_array($response['fields'])
                ? $response['fields']
                : [];
            foreach ($list as $field) {
                if (!is_array($field) || empty($field['id']) || empty($field['name'])) {
                    continue;
                }
                $name = strtolower(trim((string) $field['name']));
                $id = (int) $field['id'];
                if ($id < 1) {
                    continue;
                }
                if ($name === 'media condition') {
                    $resolved['media'] = $id;
                } elseif ($name === 'sleeve condition') {
                    $resolved['sleeve'] = $id;
                } elseif ($name === 'notes') {
                    $resolved['notes'] = $id;
                }
            }
        } catch (Exception $e) {
            // Keep default 1/2/3 ids.
        }

        if ($username !== '') {
            $this->collectionPersonalFieldIdsByUser[$username] = $resolved;
        }

        return $resolved;
    }

    /**
     * Update notes on a wantlist entry.
     *
     * @param string $username Discogs username
     * @param int|string $releaseId Discogs release ID
     * @param string $notes Wantlist notes (empty clears Discogs)
     * @return array{status:string,message:?string,http_code:int}
     * @throws Exception When API is unavailable or username/release ID is invalid
     */
    public function updateWantlistNotes($username, $releaseId, $notes) {
        if (!$this->isAvailable()) {
            throw new Exception('Discogs API is not available');
        }

        $username = trim($username);
        if ($username === '') {
            throw new Exception('Discogs username is required');
        }

        $releaseId = (int) $releaseId;
        if ($releaseId <= 0) {
            throw new Exception('Discogs release ID is required');
        }

        $body = [
            'notes' => trim((string) $notes),
        ];

        $url = $this->baseUrl . '/users/' . rawurlencode($username)
            . '/wants/' . $releaseId;

        $params = [
            'token' => $this->apiKey,
        ];

        $httpCode = 0;
        $decoded = $this->makeWriteRequest('POST', $url, $params, $httpCode, $body);

        return $this->mapFieldWriteResponse($decoded, $httpCode);
    }

    /**
     * Fetch all release IDs from a user's collection or wantlist (paginated).
     *
     * @param string $username Discogs username
     * @param string $source 'collection' or 'wantlist'
     * @return array<int, true> Associative set keyed by release ID
     */
    public function collectReleaseIdSet($username, $source) {
        $ids = [];
        $page = 1;
        $pages = 1;
        do {
            if ($source === 'wantlist') {
                $result = $this->getWantlistPage($username, $page, 100);
            } else {
                $result = $this->getCollectionPage($username, $page, 100);
            }
            foreach ($result['releases'] as $row) {
                if (!empty($row['discogs_release_id'])) {
                    $ids[(int) $row['discogs_release_id']] = true;
                }
            }
            $pages = max(1, (int) $result['pagination']['pages']);
            $page++;
        } while ($page <= $pages);
        return $ids;
    }

    /**
     * Fetch release id to folder/instance ids from the user's collection (paginated).
     *
     * When multiple collection instances share a release id, the first seen wins.
     *
     * @param string $username Discogs username
     * @return array<int, array{folder_id:int,instance_id:int}> Keyed by release id
     */
    public function collectCollectionInstanceMap($username) {
        $map = [];
        $page = 1;
        $pages = 1;
        do {
            $result = $this->getCollectionPage($username, $page, 100);
            foreach ($result['releases'] as $row) {
                if (empty($row['discogs_release_id'])) {
                    continue;
                }
                if (!isset($row['discogs_instance_id']) || !isset($row['discogs_folder_id'])) {
                    continue;
                }
                $releaseId = (int) $row['discogs_release_id'];
                if (isset($map[$releaseId])) {
                    continue;
                }
                $map[$releaseId] = [
                    'folder_id' => (int) $row['discogs_folder_id'],
                    'instance_id' => (int) $row['discogs_instance_id'],
                ];
            }
            $pages = max(1, (int) $result['pagination']['pages']);
            $page++;
        } while ($page <= $pages);
        return $map;
    }

    /**
     * POST or PUT a release to collection or wantlist; map HTTP status to export result.
     *
     * @param string $target 'collection' or 'wantlist'
     * @param string $username Discogs username
     * @param int|string $releaseId Discogs release ID
     * @return array{status:string,message:?string,http_code:int}
     * @throws Exception When API is unavailable or username/release ID is invalid
     */
    private function addReleaseWrite($target, $username, $releaseId) {
        if (!$this->isAvailable()) {
            throw new Exception('Discogs API is not available');
        }

        $username = trim($username);
        if ($username === '') {
            throw new Exception('Discogs username is required');
        }

        $releaseId = (int) $releaseId;
        if ($releaseId <= 0) {
            throw new Exception('Discogs release ID is required');
        }

        $params = [
            'token' => $this->apiKey,
        ];

        // Folder 0 is Discogs "All" (virtual). Writes go to Uncategorized (folder 1).
        if ($target === 'collection') {
            $url = $this->baseUrl . '/users/' . rawurlencode($username)
                . '/collection/folders/1/releases/' . $releaseId;
            $method = 'POST';
        } else {
            $url = $this->baseUrl . '/users/' . rawurlencode($username)
                . '/wants/' . $releaseId;
            $method = 'PUT';
        }

        $httpCode = 0;
        $decoded = $this->makeWriteRequest($method, $url, $params, $httpCode);

        if ($httpCode === 200 || $httpCode === 201) {
            $added = [
                'status' => 'added',
                'message' => null,
                'http_code' => $httpCode,
            ];
            if ($target === 'collection') {
                $instanceMeta = $this->extractAddCollectionInstanceMeta($decoded);
                if ($instanceMeta['instance_id'] > 0) {
                    $added['instance_id'] = $instanceMeta['instance_id'];
                }
                if ($instanceMeta['folder_id'] !== null) {
                    $added['folder_id'] = $instanceMeta['folder_id'];
                }
            }
            return $added;
        }

        if ($httpCode === 400 || $httpCode === 409 || $httpCode === 422) {
            return [
                'status' => 'skipped',
                'message' => $this->extractDiscogsErrorMessage($decoded),
                'http_code' => $httpCode,
            ];
        }

        return [
            'status' => 'error',
            'message' => $this->formatWriteErrorMessage($decoded, $httpCode),
            'http_code' => $httpCode,
        ];
    }

    /**
     * Read folder/instance ids from a collection add response body.
     *
     * @param array|null $decoded Decoded API response
     * @return array{instance_id:int,folder_id:?int}
     */
    private function extractAddCollectionInstanceMeta($decoded) {
        $instanceId = 0;
        $folderId = null;
        if (!is_array($decoded)) {
            return [
                'instance_id' => $instanceId,
                'folder_id' => $folderId,
            ];
        }
        // Prefer instance_id only — top-level id on collection payloads is the release id.
        if (isset($decoded['instance_id'])) {
            $instanceId = (int) $decoded['instance_id'];
        }
        if (isset($decoded['folder_id'])) {
            $folderId = (int) $decoded['folder_id'];
        }
        return [
            'instance_id' => $instanceId,
            'folder_id' => $folderId,
        ];
    }

    /**
     * Map HTTP status from collection instance or wantlist field writes.
     *
     * @param array|null $decoded Decoded API response
     * @param int $httpCode HTTP status code
     * @return array{status:string,message:?string,http_code:int}
     */
    private function mapFieldWriteResponse($decoded, $httpCode) {
        // Discogs instance/wantlist edits often return 204 No Content on success.
        if ($httpCode === 200 || $httpCode === 201 || $httpCode === 204) {
            return [
                'status' => 'updated',
                'message' => null,
                'http_code' => $httpCode,
            ];
        }

        if ($httpCode === 400 || $httpCode === 409 || $httpCode === 422) {
            return [
                'status' => 'skipped',
                'message' => $this->extractDiscogsErrorMessage($decoded),
                'http_code' => $httpCode,
            ];
        }

        return [
            'status' => 'error',
            'message' => $this->formatWriteErrorMessage($decoded, $httpCode),
            'http_code' => $httpCode,
        ];
    }

    /**
     * Pull a human-readable error string from a Discogs JSON error body.
     *
     * @param array|null $decoded Decoded API response
     * @return string|null
     */
    private function extractDiscogsErrorMessage($decoded) {
        if (!is_array($decoded)) {
            return null;
        }
        if (!empty($decoded['message']) && is_string($decoded['message'])) {
            $message = trim($decoded['message']);
            // Discogs field writes often hide the real reason under detail[].msg
            if (!empty($decoded['detail']) && is_array($decoded['detail'])) {
                $parts = [];
                foreach ($decoded['detail'] as $row) {
                    if (is_array($row) && !empty($row['msg']) && is_string($row['msg'])) {
                        $parts[] = trim($row['msg']);
                    }
                }
                if (!empty($parts)) {
                    return $message . ' (' . implode('; ', $parts) . ')';
                }
            }
            return $message;
        }
        if (!empty($decoded['error']) && is_string($decoded['error'])) {
            return trim($decoded['error']);
        }
        return null;
    }

    /**
     * Build export error message including HTTP status.
     *
     * @param array|null $decoded Decoded API response
     * @param int $httpCode HTTP status code
     * @return string
     */
    private function formatWriteErrorMessage($decoded, $httpCode) {
        $apiMessage = $this->extractDiscogsErrorMessage($decoded);
        if ($httpCode === 403) {
            $hint = ' Export username must match the Discogs account for your personal access token.';
            if ($apiMessage !== null && $apiMessage !== '') {
                return $apiMessage . ' (HTTP 403)' . $hint;
            }
            return 'Discogs refused the write (HTTP 403).' . $hint;
        }
        if ($apiMessage !== null && $apiMessage !== '') {
            return $apiMessage . ' (HTTP ' . $httpCode . ')';
        }
        return 'Discogs write failed (HTTP ' . $httpCode . ')';
    }

    /**
     * POST or PUT to Discogs; returns decoded JSON body (or null) and HTTP code via reference.
     *
     * @param string $method POST|PUT
     * @param string $url Absolute API URL without query
     * @param array $params Including token
     * @param int $httpCode Out: HTTP status
     * @param array|string|null $body JSON body; array is encoded, null sends {}
     * @param int $retryCount
     * @return array|null
     */
    private function makeWriteRequest($method, $url, $params, &$httpCode, $body = null, $retryCount = 0) {
        if (self::$lastRequestTime > 0) {
            $this->enforceRateLimit();
        }
        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $fullUrl = $url;
        if (!empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }
        if ($body === null) {
            $postFields = '{}';
        } elseif (is_array($body)) {
            $postFields = json_encode($body);
        } else {
            $postFields = (string) $body;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS => $postFields,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        self::$lastRequestTime = microtime(true) * 1000000;
        if ($httpCode === 429 && $retryCount < 3) {
            sleep([1, 3, 6][$retryCount]);
            return $this->makeWriteRequest($method, $url, $params, $httpCode, $body, $retryCount + 1);
        }
        if ($response === false || $response === '') {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Map a collection or wantlist API item to a normalized album draft.
     *
     * @param array $item Raw Discogs collection release or wantlist entry
     * @return array|null Normalized draft, or null if basic_information is missing
     */
    public function mapCollectionOrWantItem($item) {
        if (!is_array($item)) {
            return null;
        }

        $basic = isset($item['basic_information']) && is_array($item['basic_information'])
            ? $item['basic_information']
            : null;

        if ($basic === null) {
            return null;
        }

        $draft = $this->mapBasicInformationItem($basic);
        $personal = $this->extractCollectionPersonalFields($item);
        $draft['media_condition'] = $personal['media_condition'];
        $draft['sleeve_condition'] = $personal['sleeve_condition'];
        $draft['notes'] = $personal['notes'];
        // Collection list items: top-level id is the release id; instance_id is the copy id.
        if (isset($item['instance_id']) && (int) $item['instance_id'] > 0) {
            $draft['discogs_instance_id'] = (int) $item['instance_id'];
        }
        if (isset($item['folder_id'])) {
            $draft['discogs_folder_id'] = (int) $item['folder_id'];
        }
        return $draft;
    }

    /**
     * Coerce a Discogs scalar field to a trimmed string without array-to-string warnings.
     *
     * @param mixed $value
     * @return string
     */
    private function coerceDiscogsScalarString($value) {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }
        return '';
    }

    /**
     * Pull media/sleeve/notes from a collection or wantlist item.
     *
     * Collection stores these in the notes[] custom-field list (field_id 1/2/3 by default).
     * Wantlist uses a plain string notes value.
     *
     * @param array $item Raw Discogs item
     * @return array{media_condition:string,sleeve_condition:string,notes:string}
     */
    private function extractCollectionPersonalFields(array $item) {
        $media = $this->coerceDiscogsScalarString(
            isset($item['media_condition']) ? $item['media_condition'] : null
        );
        $sleeve = $this->coerceDiscogsScalarString(
            isset($item['sleeve_condition']) ? $item['sleeve_condition'] : null
        );
        $notes = '';

        if (isset($item['notes']) && (is_string($item['notes']) || is_numeric($item['notes']))) {
            $notes = $this->coerceDiscogsScalarString($item['notes']);
        } elseif (isset($item['notes']) && is_array($item['notes'])) {
            foreach ($item['notes'] as $entry) {
                if (!is_array($entry) || !isset($entry['field_id'])) {
                    continue;
                }
                $fieldId = (int) $entry['field_id'];
                $value = $this->coerceDiscogsScalarString(
                    isset($entry['value']) ? $entry['value'] : null
                );
                if ($fieldId === 1) {
                    $media = $value;
                } elseif ($fieldId === 2) {
                    $sleeve = $value;
                } elseif ($fieldId === 3) {
                    $notes = $value;
                }
            }
        }

        return [
            'media_condition' => $media,
            'sleeve_condition' => $sleeve,
            'notes' => $notes,
        ];
    }

    /**
     * Map Discogs basic_information to the local album draft shape used for import.
     *
     * @param array $basicInformation Discogs basic_information object
     * @return array Normalized album draft
     */
    public function mapBasicInformationItem($basicInformation) {
        $artistName = '';
        if (!empty($basicInformation['artists']) && is_array($basicInformation['artists'])) {
            $firstArtist = $basicInformation['artists'][0];
            if (is_array($firstArtist) && !empty($firstArtist['name'])) {
                $artistName = $this->cleanArtistName($firstArtist['name']);
            }
        }

        $albumName = isset($basicInformation['title']) ? trim((string) $basicInformation['title']) : '';

        $releaseYear = null;
        if (isset($basicInformation['year']) && $basicInformation['year'] !== '' && $basicInformation['year'] !== null) {
            $releaseYear = (int) $basicInformation['year'];
            if ($releaseYear === 0) {
                $releaseYear = null;
            }
        }

        $coverUrl = null;
        foreach (array('cover_image', 'thumb') as $coverField) {
            if (!empty($basicInformation[$coverField])) {
                $coverUrl = ImageOptimizationService::forceHttps(trim((string) $basicInformation[$coverField]));
                break;
            }
        }

        $coverImages = [];
        if ($coverUrl !== null) {
            $coverImages[] = $coverUrl;
        }

        $discogsReleaseId = 0;
        if (isset($basicInformation['id'])) {
            $discogsReleaseId = (int) $basicInformation['id'];
        }

        $format = null;
        if (!empty($basicInformation['formats']) && is_array($basicInformation['formats'])) {
            $formatDetails = $this->extractFormatDetails($basicInformation['formats']);
            $format = $formatDetails !== '' ? $formatDetails : null;
        }

        $label = null;
        if (!empty($basicInformation['labels']) && is_array($basicInformation['labels'])) {
            $firstLabel = $basicInformation['labels'][0];
            if (is_array($firstLabel) && !empty($firstLabel['name'])) {
                $label = trim((string) $firstLabel['name']);
            }
        }

        $style = null;
        if (!empty($basicInformation['styles']) && is_array($basicInformation['styles'])) {
            $style = implode(', ', $basicInformation['styles']);
        } elseif (!empty($basicInformation['style'])) {
            $style = trim((string) $basicInformation['style']);
        }

        return [
            'artist_name' => $artistName,
            'album_name' => $albumName,
            'release_year' => $releaseYear,
            'cover_url' => $coverUrl,
            'cover_images' => $coverImages,
            'discogs_release_id' => $discogsReleaseId,
            'format' => $format,
            'label' => $label,
            'style' => $style,
            'producer' => null,
            'artist_type' => null,
        ];
    }

    /**
     * Normalize pagination block from a collection/wantlist API response.
     *
     * @param array|null $response Decoded API response
     * @param int $requestedPage Page requested when response is empty or malformed
     * @return array{page:int,pages:int,items:int}
     */
    private function extractImportPagination($response, $requestedPage) {
        $page = max(1, (int) $requestedPage);
        $pages = 0;
        $items = 0;

        if (is_array($response) && isset($response['pagination']) && is_array($response['pagination'])) {
            $pagination = $response['pagination'];
            if (isset($pagination['page'])) {
                $page = (int) $pagination['page'];
            }
            if (isset($pagination['pages'])) {
                $pages = (int) $pagination['pages'];
            }
            if (isset($pagination['items'])) {
                $items = (int) $pagination['items'];
            }
        }

        return [
            'page' => $page,
            'pages' => $pages,
            'items' => $items,
        ];
    }

    /**
     * Search for artists
     */
    public function searchArtists($query, $limit = 99) {
        if (!$this->isAvailable()) {
            return [];
        }
        
        try {
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $query,
                'type' => 'artist',
                'per_page' => $limit,
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['results'])) {
                return array_map(function($artist) {
                    return [
                        'artist_name' => $this->cleanArtistName($artist['title']),
                        'id' => $artist['id'],
                        'type' => 'artist'
                    ];
                }, $response['results']);
            }
        } catch (Exception $e) {
            // API call failed, return null
            // API call failed, return empty results
        }
        
        return [];
    }
    
    /**
     * Search for albums by artist and album name
     */
    public function searchAlbumsByArtist($artistName, $query = '', $limit = 10) {
        if (!$this->isAvailable()) {
            return [];
        }

        $results = [];
        
        // If artist and album name are the same, use more targeted search strategies
        if (strtolower(trim($artistName)) === strtolower(trim($query))) {
            // Strategy 1: Search with "artist - album" format (most common Discogs format)
            $searchQuery1 = "$artistName - $query";
            $results1 = $this->performDirectSearch($searchQuery1, $limit, $query);
            $results = array_merge($results, $results1);
            
            // Strategy 2: Search with quoted album name for exact match
            $searchQuery2 = "$artistName \"$query\"";
            $results2 = $this->performDirectSearch($searchQuery2, $limit, $query);
            $results = array_merge($results, $results2);
            
            // Strategy 3: Search with artist name and album name separated
            $searchQuery3 = "$artistName $query";
            $results3 = $this->performDirectSearch($searchQuery3, $limit, $query);
            $results = array_merge($results, $results3);
        } else {
            // Use performDirectSearch for better results - simplified for performance
            $searchQuery = "$artistName $query";
            $results = $this->performDirectSearch($searchQuery, $limit, $query);
        }
        
        // Remove duplicates and limit results
        $uniqueResults = [];
        $seenIds = [];
        $duplicateCount = 0;
        foreach ($results as $result) {
            if (!isset($seenIds[$result['id']])) {
                $uniqueResults[] = $result;
                $seenIds[$result['id']] = true;
            } else {
                $duplicateCount++;
            }
            if (count($uniqueResults) >= $limit) {
                break;
            }
        }
        
        return $uniqueResults;
    }
    
    /**
     * Search albums by artist for storage (returns original URLs)
     */
    public function searchAlbumsByArtistForStorage($artistName, $query = '', $limit = 10) {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            $results = [];
            
            // For Various Artists, be more flexible in search
            if (strtolower($artistName) === 'various' || strtolower($artistName) === 'various artists') {
                // Search 1: Try with "Various" + album name
                $searchQuery1 = "Various $query";
                $results1 = $this->performDirectSearchForStorage($searchQuery1, $limit);
                $results = array_merge($results, $results1);
                
                // Search 2: Try with just the album name (for Various Artists releases)
                if (!empty($query)) {
                    $results2 = $this->performDirectSearchForStorage($query, $limit);
                    $results = array_merge($results, $results2);
                }
                
                // Search 3: Try with "Various Artists" + album name
                $searchQuery3 = "Various Artists $query";
                $results3 = $this->performDirectSearchForStorage($searchQuery3, $limit);
                $results = array_merge($results, $results3);
            } else {
                // Use performDirectSearch for better results
                $searchQuery = "$artistName $query";
                $results = $this->performDirectSearchForStorage($searchQuery, $limit, $query);
            }
            
            // Remove duplicates and limit results
            $uniqueResults = [];
            $seenIds = [];
            foreach ($results as $result) {
                if (!isset($seenIds[$result['id']])) {
                    $uniqueResults[] = $result;
                    $seenIds[$result['id']] = true;
                }
                if (count($uniqueResults) >= $limit) {
                    break;
                }
            }
            
            return $uniqueResults;
            
        } catch (Exception $e) {
            // API call failed, return null
            // API call failed, return empty results
        }
        
        return [];
    }
    
    /**
     * Perform a single search request
     */
    private function performSearch($searchQuery, $limit, $artistName = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit,
            'token' => $this->apiKey
        ];
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $filteredResults = [];
            
            foreach ($response['results'] as $release) {
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[\s\-\–]+/', '', $albumName);
                    }
                    
                    // If we still have the artist name in the title, try to extract it
                    if (empty($albumName) || $albumName === $title) {
                        // Try to find common separators
                        $separators = [' - ', ' – ', ' / ', ' : '];
                        foreach ($separators as $separator) {
                            $parts = explode($separator, $title);
                            if (count($parts) > 1) {
                                $albumName = trim($parts[1]);
                                break;
                            }
                        }
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Split search query into individual terms
                $searchTerms = array_filter(array_map('trim', explode(' ', $searchQuery)));
                
                // Check if ALL search terms appear in the album name (case insensitive)
                $albumNameLower = strtolower($albumName);
                $allTermsFound = true;
                $hasSearchTerms = false;
                foreach ($searchTerms as $term) {
                    if (!empty($term)) {
                        $hasSearchTerms = true;
                        if (strpos($albumNameLower, $term) === false) {
                            $allTermsFound = false;
                            break;
                        }
                    }
                }
                if (!$hasSearchTerms) { // If no search terms (artist-only search), don't filter by album title
                    $allTermsFound = true;
                }
                
                // Also check if the artist matches (case insensitive)
                $artistMatches = false;
                if (!empty($artistName)) {
                    // Check if the search query contains the artist name (most reliable method)
                    $searchQueryLower = strtolower($searchQuery);
                    $searchArtistLower = strtolower($artistName);
                    
                    // Try exact match first
                    $artistMatches = strpos($searchQueryLower, $searchArtistLower) !== false;
                    
                    // If that doesn't work, try partial matches (e.g., "nick cave" in "nick cave & the bad seeds")
                    if (!$artistMatches) {
                        $artistWords = explode(' ', $searchArtistLower);
                        $mainArtistWords = array_slice($artistWords, 0, 2); // Take first 2 words (e.g., "nick cave")
                        $mainArtistPhrase = implode(' ', $mainArtistWords);
                        $artistMatches = strpos($searchQueryLower, $mainArtistPhrase) !== false;
                    }
                    
                    // If that doesn't work, try the artist field from the API response
                    if (!$artistMatches && !empty($artist)) {
                        $artistLower = strtolower($artist);
                        $artistMatches = strpos($artistLower, $searchArtistLower) !== false || 
                                       strpos($searchArtistLower, $artistLower) !== false;
                    }
                    
                    // If artist field is empty but search query contains artist name, assume it matches
                    if (!$artistMatches && empty($artist) && strpos($searchQueryLower, $searchArtistLower) !== false) {
                        $artistMatches = true;
                    }
                } else {
                    $artistMatches = true; // If no artist name provided, assume it matches
                }
                
                // Only include results where all search terms appear in the album title AND artist matches
                if ($allTermsFound && $artistMatches) {
                    $art = $this->extractCoverArtFromRelease($release);
                    $filteredResults[] = [
                        'id' => $release['id'],
                        'title' => $albumName,
                        'artist' => $artist,
                        'year' => $release['year'] ?? null,
                        'cover_url' => $art['cover_url'],
                    'cover_url_medium' => $this->getCoverArtForSize($release, 'medium'),
                    'cover_images' => $art['cover_images'],
                        'type' => 'album'
                    ];
                }
            }
            
            return $filteredResults;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search for autocomplete (optimized for speed)
     */
    public function performDirectSearch($searchQuery, $limit = 99, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit, // Reduced for better performance
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
        }
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $results = [];
            
            // Extract the artist name from the search query for filtering
            $searchParts = explode(' ', $searchQuery);
            $expectedArtist = '';
            if (count($searchParts) > 1) {
                // Assume the first part is the artist name
                $expectedArtist = strtolower(trim($searchParts[0]));
            }
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If no exact word match and no substring match, skip
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        continue;
                    }
                }
                
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[-\s–—]+/', '', $albumName);
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Filter by artist if we have an expected artist
                if (!empty($expectedArtist)) {
                    $extractedArtist = strtolower(trim($artist));
                    
                    // Use very strict artist matching - require exact match or artist name contains the expected artist
                    // This prevents results like "Tom Jones" when searching for "Green"
                    if ($extractedArtist !== $expectedArtist && !str_contains($extractedArtist, $expectedArtist)) {
                        // Skip this result if the artist doesn't match
                        continue;
                    }
                    
                    // Additional check: if the expected artist is a single word, ensure it's not just a partial match
                    // This prevents "Green Day" from matching when searching for "Green"
                    if (strpos($expectedArtist, ' ') === false) {
                        // Single word artist search - check if the extracted artist starts with the expected artist
                        $artistWords = explode(' ', $extractedArtist);
                        $firstWord = $artistWords[0];
                        if ($firstWord !== $expectedArtist && !str_starts_with($firstWord, $expectedArtist)) {
                            continue; // Skip if the first word doesn't match exactly
                        }
                    }
                }
                
                // Extract format information from the release
                $formatInfo = '';
                if (isset($release['format'])) {
                    $formatInfo = $release['format'];
                } elseif (isset($release['formats']) && is_array($release['formats'])) {
                    $formatInfo = $this->extractFormatDetails($release['formats']);
                }
                
                // Skip master year fetching for autocomplete performance
                $masterYear = null;
                $art = $this->extractCoverArtFromRelease($release);
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'master_year' => $masterYear,
                    'format' => $formatInfo,
                    'cover_url' => $art['cover_url'],
                    'cover_url_medium' => $this->getCoverArtFast($release),
                    'cover_images' => $art['cover_images'],
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            // Sort results by year (older first) for autocomplete performance
            usort($results, function($a, $b) {
                return ($a['year'] ?? 0) - ($b['year'] ?? 0);
            });
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search for storage (returns original URLs) - optimized for speed
     */
    public function performDirectSearchForStorage($searchQuery, $limit = 10, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit, // Reduced for better performance
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
        }
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $results = [];
            
            // Extract the artist name from the search query for filtering
            $searchParts = explode(' ', $searchQuery);
            $expectedArtist = '';
            if (count($searchParts) > 1) {
                // Assume the first part is the artist name
                $expectedArtist = strtolower(trim($searchParts[0]));
            }
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If no exact word match and no substring match, skip
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        continue;
                    }
                }
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[-\s–—]+/', '', $albumName);
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Filter by artist if we have an expected artist
                if (!empty($expectedArtist)) {
                    $extractedArtist = strtolower(trim($artist));
                    
                    // Use very strict artist matching - require exact match or artist name contains the expected artist
                    // This prevents results like "Tom Jones" when searching for "Green"
                    if ($extractedArtist !== $expectedArtist && !str_contains($extractedArtist, $expectedArtist)) {
                        // Skip this result if the artist doesn't match
                        continue;
                    }
                    
                    // Additional check: if the expected artist is a single word, ensure it's not just a partial match
                    // This prevents "Green Day" from matching when searching for "Green"
                    if (strpos($expectedArtist, ' ') === false) {
                        // Single word artist search - check if the extracted artist starts with the expected artist
                        $artistWords = explode(' ', $extractedArtist);
                        $firstWord = $artistWords[0];
                        if ($firstWord !== $expectedArtist && !str_starts_with($firstWord, $expectedArtist)) {
                            continue; // Skip if the first word doesn't match exactly
                        }
                    }
                }
                
                // Extract format information from the release
                $formatInfo = '';
                if (isset($release['format'])) {
                    $formatInfo = $release['format'];
                } elseif (isset($release['formats']) && is_array($release['formats'])) {
                    $formatInfo = $this->extractFormatDetails($release['formats']);
                }

                $art = $this->extractCoverArtFromRelease($release);
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'master_year' => null, // Will be fetched when needed
                    'format' => $formatInfo,
                    'cover_url' => $art['cover_url'],
                    'cover_images' => $art['cover_images'],
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            // Sort results by year (older first) for autocomplete performance
            usort($results, function($a, $b) {
                return ($a['year'] ?? 0) - ($b['year'] ?? 0);
            });
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Get cover art for a release (fast version without validation)
     */
    private function getCoverArtFast($release) {
        // Try different possible cover art fields, prioritizing uri150 for thumbnails
        $coverFields = ['uri150', 'cover_image', 'thumb', 'image'];
        
        foreach ($coverFields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Skip validation for speed - just return the URL
                return $coverUrl;
            }
        }
        
        return null;
    }
    
    /**
     * Get cover art for a release (full size)
     */
    private function getCoverArt($release) {
        // Try different possible cover art fields, prioritizing full-size images
        $coverFields = ['cover_image', 'thumb', 'image', 'uri150'];
        
        foreach ($coverFields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Check if the image URL is valid
                if ($this->isValidImageUrl($coverUrl)) {
                    return $coverUrl;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get optimized cover art URL for specific size
     */
    private function getCoverArtForSize($release, $size = 'thumbnail') {
        // Map sizes to Discogs URI fields
        $sizeMap = [
            'thumbnail' => ['uri150', 'thumb'],
            'medium' => ['uri500', 'uri150', 'thumb'],
            'large' => ['cover_image', 'image', 'uri500', 'uri150']
        ];
        
        $fields = $sizeMap[$size] ?? ['cover_image', 'thumb', 'image'];
        
        foreach ($fields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Skip validation for speed - just return the URL
                return $coverUrl;
            }
        }
        
        // Fallback to the fast method
        return $this->getCoverArtFast($release);
    }

    /**
     * Build cover_url (thumb) + cover_images (all full URIs) from a Discogs release payload.
     * Primary image is first in cover_images; remaining images keep Discogs order.
     *
     * @param array $release Discogs release (or search-like) array
     * @return array{cover_url:?string,cover_images:array}
     */
    public function extractCoverArtFromRelease($release) {
        $coverUrl = null;
        $coverImages = [];
        $seen = [];

        $images = isset($release['images']) ? $release['images'] : null;

        // Explicit empty images[] — no fallbacks to release-level cover fields.
        if (is_array($images) && empty($images)) {
            return [
                'cover_url' => null,
                'cover_images' => [],
            ];
        }

        // No usable images[] — only explicit full-size release fields (never resized URIs).
        if (!is_array($images)) {
            foreach (array('cover_image', 'image') as $field) {
                if (empty($release[$field])) {
                    continue;
                }
                $uri = trim($release[$field]);
                if ($uri === '') {
                    continue;
                }
                $coverImages[] = ImageOptimizationService::forceHttps($uri);
                break;
            }
            return [
                'cover_url' => null,
                'cover_images' => $coverImages,
            ];
        }
        $primaryIndex = null;
        foreach ($images as $i => $image) {
            if (isset($image['type']) && $image['type'] === 'primary') {
                $primaryIndex = $i;
                break;
            }
        }
        if ($primaryIndex === null) {
            $primaryIndex = 0;
        }

        $ordered = [];
        $ordered[] = $images[$primaryIndex];
        foreach ($images as $i => $image) {
            if ($i === $primaryIndex) {
                continue;
            }
            $ordered[] = $image;
        }

        foreach ($ordered as $index => $image) {
            $uri = isset($image['uri']) ? trim($image['uri']) : '';
            if ($uri === '') {
                continue;
            }
            $uri = ImageOptimizationService::forceHttps($uri);
            if (isset($seen[$uri])) {
                continue;
            }
            $seen[$uri] = true;
            $coverImages[] = $uri;

            // cover_url only from explicit uri150 on an included image; no other fallbacks.
            if ($coverUrl === null && !empty($image['uri150'])) {
                $uri150 = trim($image['uri150']);
                if ($uri150 !== '') {
                    $coverUrl = ImageOptimizationService::forceHttps($uri150);
                }
            }
        }

        return [
            'cover_url' => $coverUrl,
            'cover_images' => $coverImages,
        ];
    }
    
    /**
     * Check if an image URL is valid
     */
    private function isValidImageUrl($url) {
        if (empty($url) || $url === 'https://img.discogs.com/') {
            return false;
        }
        
        // Force HTTPS for validation
        $url = ImageOptimizationService::forceHttps($url);
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: ' . $this->userAgent]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            // API call failed, return null
            return false;
        }
    }
    
    /**
     * Make HTTP request to Discogs API with rate limiting and retry logic
     */
    private function makeRequest($url, $params = [], $retryCount = 0) {
        // Rate limiting: ensure we don't make requests too frequently
        // Skip rate limiting for the first request to avoid hanging during initialization
        if (self::$lastRequestTime > 0) {
            $this->enforceRateLimit();
        }
        
        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json'
        ];
        
        $fullUrl = $url;
        if (!empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }
        
        if (!function_exists('curl_init')) {
            throw new Exception('Discogs API request failed: PHP curl extension is not available');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            // Host CA bundles vary; peer verify off matches prior behavior.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            // Prefer IPv4 — some hosts fail Discogs over broken IPv6 (HTTP code 0)
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            // Cloudflare + PHP libcurl often fails HTTP/2 with HTTP code 0; force 1.1.
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
        
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        // Handle rate limiting (429) with custom retry intervals
        if ($httpCode === 429 && $retryCount < 3) {
            $retryDelays = [1, 3, 6]; // 1, 3, 6 seconds
            $waitTime = $retryDelays[$retryCount];
            sleep($waitTime);
            return $this->makeRequest($url, $params, $retryCount + 1);
        }
        
        // Handle other errors gracefully
        if ($httpCode === 429) {
            return null; // Return null instead of throwing exception
        }

        // HTTP 0 means the TCP/TLS connection never completed — include curl details.
        if ($httpCode === 0 || $response === false) {
            $detail = $curlError !== '' ? $curlError : 'unknown connection error';
            throw new Exception(
                "Discogs API request failed with HTTP code: 0 (curl {$curlErrno}: {$detail})"
            );
        }
        
        throw new Exception("Discogs API request failed with HTTP code: $httpCode");
    }

    /**
     * GET Discogs and return decoded JSON for any HTTP status (used for identity checks).
     *
     * @param string $url Absolute API URL without query
     * @param array $params Query params including token
     * @param int $httpCode Out: HTTP status
     * @param int $retryCount
     * @return array|null
     */
    private function makeRequestWithStatus($url, $params, &$httpCode, $retryCount = 0) {
        if (self::$lastRequestTime > 0) {
            $this->enforceRateLimit();
        }

        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json',
        ];
        $fullUrl = $url;
        if (!empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }

        if (!function_exists('curl_init')) {
            throw new Exception('Discogs API request failed: PHP curl extension is not available');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        self::$lastRequestTime = microtime(true) * 1000000;

        if ($httpCode === 429 && $retryCount < 3) {
            sleep([1, 3, 6][$retryCount]);
            return $this->makeRequestWithStatus($url, $params, $httpCode, $retryCount + 1);
        }

        if ($httpCode === 0 || $response === false) {
            $detail = $curlError !== '' ? $curlError : 'unknown connection error';
            throw new Exception(
                "Discogs API request failed with HTTP code: 0 (curl {$curlErrno}: {$detail})"
            );
        }

        if ($response === '' || $response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
    
    /**
     * Enforce rate limiting between API requests
     */
    private function enforceRateLimit() {
        $currentTime = microtime(true) * 1000000; // Convert to microseconds
        $timeSinceLastRequest = $currentTime - self::$lastRequestTime;
        
        if ($timeSinceLastRequest < self::$requestDelay) {
            $sleepTime = self::$requestDelay - $timeSinceLastRequest;
            usleep($sleepTime);
        }
        
        self::$lastRequestTime = microtime(true) * 1000000;
    }
    
    /**
     * Extract detailed format information from Discogs formats array
     */
    private function extractFormatDetails($formats) {
        if (empty($formats)) {
            return '';
        }
        
        $formatParts = [];
        
        foreach ($formats as $format) {
            $formatInfo = [];
            
            // Add the main format name (e.g., "Vinyl", "CD", "Cassette")
            if (isset($format['name'])) {
                $formatInfo[] = $format['name'];
            }
            
            // Add descriptive text (e.g., "7"", "10"", "45 RPM", "33 ⅓ RPM")
            if (isset($format['descriptions']) && is_array($format['descriptions'])) {
                $formatInfo = array_merge($formatInfo, $format['descriptions']);
            }
            
            // Add quantity if more than 1
            if (isset($format['qty']) && $format['qty'] > 1) {
                $formatInfo[] = "×{$format['qty']}";
            }
            
            // Add text field if present (additional format details)
            if (isset($format['text']) && !empty($format['text'])) {
                $formatInfo[] = $format['text'];
            }
            
            $formatParts[] = implode(', ', $formatInfo);
        }
        
        return implode(' + ', $formatParts);
    }

    /**
     * Fetch thumbnail + all cover image URIs for a release (no tracklist/marketplace/master calls).
     *
     * @param int|string $releaseId Discogs release ID
     * @return array{thumb:?string,cover_images:string[]}|null
     */
    public function getCoverUrlsByReleaseId($releaseId) {
        if (!$this->isAvailable() || empty($releaseId)) {
            return null;
        }

        try {
            $url = $this->baseUrl . '/releases/' . intval($releaseId);
            $response = $this->makeRequest($url, [
                'token' => $this->apiKey
            ]);

            if ($response) {
                $art = $this->extractCoverArtFromRelease($response);
                if (empty($art['cover_url']) && empty($art['cover_images'])) {
                    return null;
                }
                return [
                    'thumb' => $art['cover_url'],
                    'cover_images' => $art['cover_images'],
                ];
            }
        } catch (Exception $e) {
            // Leave null; caller decides whether to keep the existing cover
        }

        return null;
    }

    /**
     * Fetch only the primary full-size cover URL for a release.
     *
     * @param int|string $releaseId Discogs release ID
     * @return string|null HTTPS cover URL, or null on failure
     */
    public function getLargeCoverUrlByReleaseId($releaseId) {
        $urls = $this->getCoverUrlsByReleaseId($releaseId);
        if (!$urls || empty($urls['cover_images'][0])) {
            return null;
        }
        return $urls['cover_images'][0];
    }
    
    /**
     * Get detailed release information including tracklist
     *
     * @param int|string $releaseId Discogs release ID
     * @param bool $includeExtras When false, skip reviews/master/marketplace round-trips
     *                           so the tracklist can be returned from a single Discogs call.
     */
    public function getReleaseInfo($releaseId, $includeExtras = true) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        $fullCacheKey = "release_{$releaseId}";
        $basicCacheKey = "release_{$releaseId}_basic";

        // A full cached payload is always usable, including for the fast path.
        if (isset(self::$cache[$fullCacheKey]) && self::$cache[$fullCacheKey]['expiry'] > time()) {
            return self::$cache[$fullCacheKey]['data'];
        }

        if (!$includeExtras && isset(self::$cache[$basicCacheKey]) && self::$cache[$basicCacheKey]['expiry'] > time()) {
            return self::$cache[$basicCacheKey]['data'];
        }
        
        try {
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['title'])) {
                $art = $this->extractCoverArtFromRelease($response);
                // Extract tracklist information
                $tracklist = [];
                if (isset($response['tracklist']) && is_array($response['tracklist'])) {
                    foreach ($response['tracklist'] as $track) {
                        $tracklist[] = [
                            'position' => $track['position'] ?? '',
                            'title' => $track['title'] ?? '',
                            'duration' => $track['duration'] ?? ''
                        ];
                    }
                }
                
                // Extract detailed format information
                $formatDetails = $this->extractFormatDetails($response['formats'] ?? []);
                
                // Extract producer information from companies
                $producers = [];
                if (isset($response['companies']) && is_array($response['companies'])) {
                    foreach ($response['companies'] as $company) {
                        if (isset($company['entity_type_name']) && 
                            $company['entity_type_name'] === 'Producer') {
                            $producers[] = $company['name'];
                        }
                    }
                }
                
                // Also check extraartists as fallback
                if (empty($producers) && isset($response['extraartists']) && is_array($response['extraartists'])) {
                    foreach ($response['extraartists'] as $extraArtist) {
                        if (isset($extraArtist['role']) && 
                            (stripos($extraArtist['role'], 'Producer') !== false || 
                             stripos($extraArtist['role'], 'Production') !== false)) {
                            $producers[] = $extraArtist['name'];
                        }
                    }
                }
                

                

                
                $ratingCount = isset($response['community']['rating']['count']) ? $response['community']['rating']['count'] : null;

                // Extra Discogs round-trips are optional; the tracklist itself is in this payload.
                $hasReviewsWithContent = !empty($ratingCount);
                $masterYear = null;
                $masterReleased = null;
                $marketStats = [];
                if ($includeExtras) {
                    $hasReviewsWithContent = $this->hasReviewsWithContent($releaseId);
                    if (isset($response['master_id']) && $response['master_id']) {
                        $masterInfo = $this->getMasterReleaseInfo($response['master_id']);
                        $masterYear = $masterInfo['year'] ?? null;
                        $masterReleased = $masterInfo['released'] ?? null;
                    }
                    $marketStats = $this->getMarketplaceStats($releaseId);
                }
                
                // Calculate total runtime from tracklist
                $totalRuntime = $this->calculateTotalRuntime($tracklist);
                
                // Determine the released date to use
                $releasedDate = null;
                if ($masterReleased) {
                    // Use master release date if available
                    $releasedDate = $masterReleased;
                } elseif ($masterYear) {
                    // Use master release year if no specific date is available
                    $releasedDate = $masterYear;
                } else {
                    // Fall back to specific release date
                    $releasedDate = $response['released'] ?? null;
                }
                
                $marketNumForSale = $marketStats['num_for_sale'] ?? ($response['num_for_sale'] ?? null);
                // Discogs release payload may provide lowest_price as a number without currency; prefer stats when available
                $marketLowestPrice = null;
                if (isset($marketStats['lowest_price'])) {
                    $marketLowestPrice = $marketStats['lowest_price']; // ['amount' => float, 'currency' => 'USD']
                } elseif (isset($response['lowest_price']) && is_numeric($response['lowest_price'])) {
                    $marketLowestPrice = [
                        'amount' => $response['lowest_price'],
                        'currency' => null
                    ];
                }

                $result = [
                    'title' => $response['title'],
                    'artist' => $response['artists'][0]['name'] ?? '',
                    'year' => $response['year'] ?? null,
                    'master_id' => $response['master_id'] ?? null,
                    'master_year' => $masterYear,
                    'cover_url' => $art['cover_url'],
                    'cover_images' => $art['cover_images'],
                    'tracklist' => $tracklist,
                    'format' => $formatDetails,
                    'producer' => !empty($producers) ? implode(', ', array_unique($producers)) : '',
                    'rating' => isset($response['community']['rating']['average']) ? $response['community']['rating']['average'] : null,
                    'rating_count' => $ratingCount,
                    'has_reviews_with_content' => $hasReviewsWithContent,
                    'style' => isset($response['styles']) ? implode(', ', $response['styles']) : '',
                    'label' => $response['labels'][0]['name'] ?? '',
                    'released' => $releasedDate,
                    'total_runtime' => $totalRuntime,
                    // Marketplace fields (prefer stats endpoint; fallback to release fields)
                    'num_for_sale' => $marketNumForSale,
                    'lowest_price' => $marketLowestPrice
                ];
                
                $cacheKey = $includeExtras ? $fullCacheKey : $basicCacheKey;
                self::$cache[$cacheKey] = [
                    'data' => $result,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $result;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }

    /**
     * Fetch optional Discogs extras after the tracklist is already shown.
     *
     * @param int|string $releaseId
     * @param string $artistName
     * @param int|string|null $masterId
     * @param string|int|null $cachedPressingYear Skip Discogs year lookup when already cached
     * @param array|null $cachedArtistWebsite Use cached artist links; skip getArtistWebsite when array
     * @return array
     */
    public function getTracklistExtras($releaseId, $artistName = '', $masterId = null, $cachedPressingYear = null, $cachedArtistWebsite = null) {
        $masterYear = null;
        $masterReleased = null;
        if (!empty($masterId)) {
            $masterInfo = $this->getMasterReleaseInfo($masterId);
            if ($masterInfo) {
                $masterYear = $masterInfo['year'] ?? null;
                $masterReleased = $masterInfo['released'] ?? null;
            }
        }

        $marketStats = $this->getMarketplaceStats($releaseId);
        $artistWebsite = null;
        if (is_array($cachedArtistWebsite)) {
            $artistWebsite = $cachedArtistWebsite;
        } elseif ($artistName !== '') {
            $artistWebsite = $this->getArtistWebsite($artistName);
        }

        $rating = null;
        $ratingCount = null;
        $releaseRating = $this->getReleaseCommunityRating($releaseId);
        if ($releaseRating) {
            $rating = isset($releaseRating['average']) ? $releaseRating['average'] : null;
            $ratingCount = isset($releaseRating['count']) ? $releaseRating['count'] : null;
        }

        // Prefer album-cached pressing year; only hit Discogs when missing
        $pressingYear = null;
        if ($cachedPressingYear !== null && $cachedPressingYear !== '') {
            $pressingYear = $cachedPressingYear;
        } else {
            $basicRelease = $this->getReleaseInfo($releaseId, false);
            if ($basicRelease && isset($basicRelease['year']) && $basicRelease['year'] !== '' && $basicRelease['year'] !== null) {
                $pressingYear = $basicRelease['year'];
            }
        }

        return [
            'master_year' => $masterYear,
            'pressing_year' => $pressingYear,
            'released' => $masterReleased ?: $masterYear,
            'rating' => $rating,
            'rating_count' => $ratingCount,
            'has_reviews_with_content' => $this->hasReviewsWithContent($releaseId),
            'num_for_sale' => $marketStats['num_for_sale'] ?? null,
            'lowest_price' => $marketStats['lowest_price'] ?? null,
            'artist_website' => $artistWebsite,
        ];
    }

    /**
     * Community rating average/count for a release (used by tracklist enrich).
     *
     * @param int|string $releaseId
     * @return array|null
     */
    private function getReleaseCommunityRating($releaseId) {
        if (!$this->isAvailable() || empty($releaseId)) {
            return null;
        }

        $cacheKey = "release_rating_{$releaseId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }

        try {
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $response = $this->makeRequest($url, [
                'token' => $this->apiKey
            ]);
            if ($response && isset($response['community']['rating'])) {
                $data = [
                    'average' => isset($response['community']['rating']['average'])
                        ? $response['community']['rating']['average']
                        : null,
                    'count' => isset($response['community']['rating']['count'])
                        ? $response['community']['rating']['count']
                        : null,
                ];
                self::$cache[$cacheKey] = [
                    'data' => $data,
                    'expiry' => time() + 600
                ];
                return $data;
            }
        } catch (Exception $e) {
            // Optional enrich field
        }

        return null;
    }
    
    /**
     * Check if a release has reviews with content
     */
    private function hasReviewsWithContent($releaseId) {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $url = $this->baseUrl . "/releases/{$releaseId}/reviews";
            $params = [
                'token' => $this->apiKey,
                'per_page' => 3 // Check a few reviews to see if any have content
            ];
            
            $response = $this->makeRequest($url, $params);

            if ($response && isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $review) {
                    if (!empty($review['review_plaintext']) || !empty($review['review_html'])) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return false;
    }

    /**
     * Get marketplace stats for a release (num_for_sale, lowest_price)
     */
    private function getMarketplaceStats($releaseId) {
        if (!$this->isAvailable()) {
            return [];
        }
        
        // Check cache first
        $cacheKey = "market_stats_{$releaseId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            $url = $this->baseUrl . "/marketplace/stats/{$releaseId}";
            $params = [
                'token' => $this->apiKey,
                // Request pricing in preferred currency
                'curr_abbr' => $this->preferredCurrency ?: (defined('DISCOGS_CURRENCY') ? DISCOGS_CURRENCY : 'USD')
            ];
            $response = $this->makeRequest($url, $params);
            
            if ($response && (isset($response['num_for_sale']) || isset($response['lowest_price']))) {
                $result = [
                    'num_for_sale' => $response['num_for_sale'] ?? null,
                    'lowest_price' => null
                ];
                
                if (isset($response['lowest_price'])) {
                    // Stats endpoint returns an object { value, currency }
                    if (is_array($response['lowest_price'])) {
                        $result['lowest_price'] = [
                            'amount' => $response['lowest_price']['value'] ?? null,
                            'currency' => $response['lowest_price']['currency'] ?? null
                        ];
                    } elseif (is_numeric($response['lowest_price'])) {
                        $result['lowest_price'] = [
                            'amount' => $response['lowest_price'],
                            'currency' => null
                        ];
                    }
                }
                
                // Cache for shorter period since marketplace changes faster
                self::$cache[$cacheKey] = [
                    'data' => $result,
                    'expiry' => time() + 900 // 15 minutes
                ];
                
                return $result;
            }
        } catch (Exception $e) {
            // Fail quietly; marketplace info is optional
        }
        
        return [];
    }
    
    /**
     * Get master release information from master release ID
     */
    private function getMasterReleaseInfo($masterId) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "master_{$masterId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            $url = $this->baseUrl . "/masters/{$masterId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && (isset($response['year']) || isset($response['released']))) {
                $result = [
                    'year' => $response['year'] ?? null,
                    'released' => $response['released'] ?? null
                ];
                
                // Cache the result
                self::$cache[$cacheKey] = [
                    'data' => $result,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $result;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }
    
    /**
     * Get master year for a release ID (lightweight version)
     */
    public function getMasterYear($releaseId) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "master_year_{$releaseId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            // Get basic release info to find master_id
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['master_id']) && $response['master_id']) {
                // Get master year from master release
                $masterInfo = $this->getMasterReleaseInfo($response['master_id']);
                $masterYear = $masterInfo['year'] ?? null;
                
                // Cache the result
                self::$cache[$cacheKey] = [
                    'data' => $masterYear,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $masterYear;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }
    
    /**
     * Get artist information including type (group/person) from Discogs
     */
    public function getArtistInfo($artistName) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Special case for "Various Artists" - classify as Group
        if (strtolower(trim($artistName)) === 'various artists') {
            return [
                'type' => 'Group',
                'match_score' => 1.0
            ];
        }
        
        // Check cache first
        $cacheKey = "artist_info_" . md5($artistName);
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            // Search for the artist with more flexible matching
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $artistName,
                'type' => 'artist',
                'per_page' => 5, // Get more results to find better matches
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['results']) && !empty($response['results'])) {
                // Find the best match by comparing artist names
                $bestMatch = null;
                $bestScore = 0;
                
                foreach ($response['results'] as $artist) {
                    $artistTitle = $artist['title'] ?? '';
                    $score = $this->calculateArtistMatchScore($artistName, $artistTitle);
                    
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $artist;
                    }
                }
                
                // Only proceed if we have a reasonable match (score > 0.7)
                if ($bestMatch && $bestScore > 0.7) {
                    $artistId = $bestMatch['id'];
                    $artistUrl = $this->baseUrl . "/artists/{$artistId}";
                    $artistParams = [
                        'token' => $this->apiKey
                    ];
                    
                    $artistResponse = $this->makeRequest($artistUrl, $artistParams);
                    
                                    if ($artistResponse) {
                    // Parse the descriptive type text to determine if it's a group or person
                    // Use 'profile' field which contains the description, not 'type' field
                    $typeText = $artistResponse['profile'] ?? '';
                    $artistType = $this->parseArtistTypeFromDescription($typeText, $artistName);
                    
                    $result = [
                        'name' => $artistResponse['name'] ?? $artistName,
                        'type' => $artistType, // 'Group' or 'Person'
                        'id' => $artistId,
                        'match_score' => $bestScore,
                        'raw_response' => $artistResponse // Include full response for debugging
                    ];
                        
                        // Cache the result
                        self::$cache[$cacheKey] = [
                            'data' => $result,
                            'expiry' => time() + self::$cacheExpiry
                        ];
                        
                        return $result;
                    }
                }
            }
        } catch (Exception $e) {
            // API error occurred
        }
        
        return null;
    }
    
    /**
     * Get artist website information from Discogs
     */
    public function getArtistWebsite($artistName) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "artist_website_" . md5($artistName);
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            // Search for the artist
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $artistName,
                'type' => 'artist',
                'per_page' => 5,
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['results']) && !empty($response['results'])) {
                // Find the best match
                $bestMatch = null;
                $bestScore = 0;
                
                foreach ($response['results'] as $artist) {
                    $artistTitle = $artist['title'] ?? '';
                    $score = $this->calculateArtistMatchScore($artistName, $artistTitle);
                    
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $artist;
                    }
                }
                
                // Only proceed if we have a reasonable match (score > 0.7)
                if ($bestMatch && $bestScore > 0.7) {
                    $artistId = $bestMatch['id'];
                    $artistUrl = $this->baseUrl . "/artists/{$artistId}";
                    $artistParams = [
                        'token' => $this->apiKey
                    ];
                    
                    $artistResponse = $this->makeRequest($artistUrl, $artistParams);
                    
                    if ($artistResponse) {
                        $websites = [];
                        
                        // Check for URLs in the response (Discogs returns a simple array of URL strings)
                        // Only include specific, useful website types
                        if (isset($artistResponse['urls']) && is_array($artistResponse['urls'])) {
                            foreach ($artistResponse['urls'] as $url) {
                                if (filter_var($url, FILTER_VALIDATE_URL)) {
                                    $domain = parse_url($url, PHP_URL_HOST);
                                    $type = null;
                                    
                                    // Only include specific, useful website types
                                    if (strpos($domain, 'facebook.com') !== false) {
                                        $type = 'Facebook';
                                    } elseif (strpos($domain, 'twitter.com') !== false || strpos($domain, 'x.com') !== false) {
                                        $type = 'Twitter';
                                    } elseif (strpos($domain, 'instagram.com') !== false) {
                                        $type = 'Instagram';
                                    } elseif (strpos($domain, 'youtube.com') !== false) {
                                        $type = 'YouTube';
                                    } elseif (strpos($domain, 'bandcamp.com') !== false) {
                                        $type = 'Bandcamp';
                                    } elseif (strpos($domain, 'soundcloud.com') !== false) {
                                        $type = 'SoundCloud';
                                    } elseif (strpos($domain, 'wikipedia.org') !== false) {
                                        $type = 'Wikipedia';
                                    } elseif (strpos($domain, 'last.fm') !== false) {
                                        $type = 'Last.fm';
                                    } elseif (strpos($domain, 'imdb.com') !== false) {
                                        $type = 'IMDb';
                                    } elseif (strpos($domain, 'bsky.app') !== false) {
                                        $type = 'Bluesky';
                                    } elseif (strpos($domain, 'discogs.com') !== false) {
                                        $type = 'Discogs';
                                    } else {
                                        // Check if it's likely the artist's official website
                                        $officialPatterns = [
                                            // Common artist website patterns with various TLDs
                                            strtolower($artistName) . '.com',
                                            strtolower($artistName) . '.net',
                                            strtolower($artistName) . '.org',
                                            strtolower($artistName) . '.co.uk',
                                            strtolower($artistName) . '.uk',
                                            strtolower($artistName) . '.de',
                                            strtolower($artistName) . '.fr',
                                            strtolower($artistName) . '.ca',
                                            strtolower($artistName) . '.au',
                                            // Remove spaces and special characters for comparison
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.com',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.net',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.org',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.co.uk',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.uk',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.de',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.fr',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.ca',
                                            strtolower(str_replace([' ', '&', '.', '-', "'", '"'], '', $artistName)) . '.au',
                                        ];
                                        
                                        $isOfficialWebsite = false;
                                        foreach ($officialPatterns as $pattern) {
                                            if (strpos($domain, $pattern) !== false) {
                                                $isOfficialWebsite = true;
                                                break;
                                            }
                                        }
                                        
                                        if ($isOfficialWebsite) {
                                            $type = 'Official Website';
                                        }
                                    }
                                    
                                    // Only add the website if it matches one of our allowed types
                                    if ($type !== null) {
                                        $websites[] = [
                                            'url' => $url,
                                            'type' => $type
                                        ];
                                    }
                                }
                            }
                        }
                        
                        $result = [
                            'name' => $artistResponse['name'] ?? $artistName,
                            'websites' => $websites,
                            'discogs_url' => "https://www.discogs.com/artist/{$artistId}",
                            'match_score' => $bestScore
                        ];
                        
                        // Cache the result
                        self::$cache[$cacheKey] = [
                            'data' => $result,
                            'expiry' => time() + self::$cacheExpiry
                        ];
                        
                        return $result;
                    }
                }
            }
        } catch (Exception $e) {
            // API error occurred
        }
        
        return null;
    }
    
    /**
     * Calculate a match score between two artist names
     */
    private function calculateArtistMatchScore($searchName, $discogsName) {
        $searchLower = strtolower(trim($searchName));
        $discogsLower = strtolower(trim($discogsName));
        
        // Exact match
        if ($searchLower === $discogsLower) {
            return 1.0;
        }
        
        // Remove common prefixes for comparison
        $searchClean = $this->removeCommonPrefixes($searchLower);
        $discogsClean = $this->removeCommonPrefixes($discogsLower);
        
        if ($searchClean === $discogsClean) {
            return 0.95;
        }
        
        // Check if one contains the other
        if (strpos($searchClean, $discogsClean) !== false || strpos($discogsClean, $searchClean) !== false) {
            return 0.8;
        }
        
        // Calculate similarity using similar_text
        similar_text($searchClean, $discogsClean, $percent);
        return $percent / 100;
    }
    
    /**
     * Remove common prefixes from artist names
     */
    private function removeCommonPrefixes($name) {
        $prefixes = ['the ', 'a ', 'an '];
        foreach ($prefixes as $prefix) {
            if (strpos($name, $prefix) === 0) {
                $name = substr($name, strlen($prefix));
            }
        }
        return trim($name);
    }
    
    /**
     * Calculate total runtime from tracklist durations
     */
    private function calculateTotalRuntime($tracklist) {
        if (empty($tracklist) || !is_array($tracklist)) {
            return null;
        }
        
        $totalSeconds = 0;
        $validTracks = 0;
        
        foreach ($tracklist as $track) {
            $duration = $track['duration'] ?? '';
            if (!empty($duration)) {
                $seconds = $this->parseDurationToSeconds($duration);
                if ($seconds > 0) {
                    $totalSeconds += $seconds;
                    $validTracks++;
                }
            }
        }
        
        // Only return total runtime if we have at least one valid track duration
        if ($validTracks > 0) {
            return $this->formatSecondsToDuration($totalSeconds);
        }
        
        return null;
    }
    
    /**
     * Parse duration string (mm:ss, mmm:ss, or hh:mm:ss) to seconds
     */
    private function parseDurationToSeconds($duration) {
        if (empty($duration)) {
            return 0;
        }
        
        // Remove any whitespace
        $duration = trim($duration);
        
        // Handle different duration formats
        if (preg_match('/^(\d+):(\d{2})$/', $duration, $matches)) {
            // Format: mm:ss or mmm:ss
            $minutes = (int)$matches[1];
            $seconds = (int)$matches[2];
            return ($minutes * 60) + $seconds;
        } elseif (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $duration, $matches)) {
            // Format: hh:mm:ss
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }
        
        return 0;
    }
    
    /**
     * Format seconds to duration string (hh:mm:ss or mm:ss)
     */
    private function formatSecondsToDuration($totalSeconds) {
        if ($totalSeconds < 0) {
            return '0:00';
        }
        
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }
    
    /**
     * Parse artist type from Discogs description text
     */
    private function parseArtistTypeFromDescription($typeText, $artistName) {
        if (empty($typeText)) {
            return 'unknown';
        }
        
        $typeLower = strtolower($typeText);
        
        // Keywords that indicate a group/band
        $groupKeywords = [
            'band', 'group', 'ensemble', 'collective', 'orchestra', 'choir', 'quartet', 'trio', 'duo',
            'founded', 'formed', 'established', 'started', 'created', 'began', 'originated',
            'members', 'lineup', 'consisting', 'comprising', 'featuring', 'including'
        ];
        
        // Keywords that indicate an individual person
        $personKeywords = [
            'singer', 'musician', 'songwriter', 'composer', 'artist', 'performer', 'vocalist',
            'born', 'died', 'birth', 'death', 'age', 'aged', 'lived', 'resided',
            'solo', 'individual', 'person', 'man', 'woman', 'male', 'female'
        ];
        
        // Count matches for each type
        $groupScore = 0;
        $personScore = 0;
        
        foreach ($groupKeywords as $keyword) {
            if (strpos($typeLower, $keyword) !== false) {
                $groupScore++;
            }
        }
        
        foreach ($personKeywords as $keyword) {
            if (strpos($typeLower, $keyword) !== false) {
                $personScore++;
            }
        }
        
        // Determine type based on scores
        if ($groupScore > $personScore) {
            return 'Group';
        } elseif ($personScore > $groupScore) {
            return 'Person';
        } else {
            // If scores are equal, use fallback logic based on artist name
            $nameParts = explode(' ', $artistName);
            if (count($nameParts) === 2) {
                // Two words - likely a person
                return 'Person';
            } else {
                // Multiple words or single word - likely a group
                return 'Group';
            }
        }
    }
}
?> 