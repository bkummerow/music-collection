<?php
/**
 * Match and merge Discogs collection/wantlist drafts into the local catalog.
 */

require_once __DIR__ . '/../models/MusicCollection.php';

class DiscogsImportService {
    /** @var MusicCollection */
    private $collection;

    /**
     * @param MusicCollection|null $collection
     */
    public function __construct(MusicCollection $collection = null) {
        $this->collection = $collection ?: new MusicCollection();
    }

    /**
     * Ownership flags for a phase and optional existing row.
     *
     * @param string $phase collection|wantlist
     * @param array|null $existing Existing album row or null for insert
     * @return array{is_owned:int,want_to_own:int}
     */
    public static function resolveFlags($phase, $existing) {
        if ($phase === 'collection') {
            return [
                'is_owned' => 1,
                'want_to_own' => 0,
            ];
        }

        if ($phase === 'wantlist') {
            if ($existing !== null && !empty($existing['is_owned'])) {
                return [
                    'is_owned' => 1,
                    'want_to_own' => 0,
                ];
            }

            return [
                'is_owned' => 0,
                'want_to_own' => 1,
            ];
        }

        throw new InvalidArgumentException('Unknown import phase: ' . $phase);
    }

    /**
     * Metadata fields eligible for fill-empty-only merge from a Discogs draft.
     *
     * @return string[]
     */
    public static function metadataFieldNames() {
        return [
            'discogs_release_id',
            'cover_url',
            'cover_images',
            'release_year',
            'style',
            'format',
            'label',
            'producer',
        ];
    }

    /**
     * Whether a stored metadata field is considered empty locally.
     *
     * @param array $existing Album row
     * @param string $field Field name
     * @return bool
     */
    public static function isMetadataFieldEmpty($existing, $field) {
        if (!isset($existing[$field])) {
            return true;
        }

        $value = $existing[$field];

        if ($field === 'discogs_release_id') {
            return $value === null || $value === '' || (int) $value === 0;
        }

        if ($field === 'release_year') {
            return $value === null || $value === '' || (int) $value === 0;
        }

        if ($field === 'cover_images') {
            return !is_array($value) || count($value) === 0;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return $value === null;
    }

    /**
     * Whether a draft value is present and usable for merge.
     *
     * @param string $field Field name
     * @param mixed $value Draft value
     * @return bool
     */
    public static function hasDraftMetadataValue($field, $value) {
        if ($field === 'discogs_release_id') {
            return $value !== null && $value !== '' && (int) $value !== 0;
        }

        if ($field === 'release_year') {
            return $value !== null && $value !== '' && (int) $value !== 0;
        }

        if ($field === 'cover_images') {
            return is_array($value) && count($value) > 0;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }

    /**
     * Fields to set on update when local values are empty and the draft supplies data.
     *
     * @param array $existing Album row
     * @param array $draft Normalized Discogs draft
     * @return array<string,mixed>
     */
    public static function shouldUpdateMetadata($existing, $draft) {
        $updates = [];

        foreach (self::metadataFieldNames() as $field) {
            if (!self::isMetadataFieldEmpty($existing, $field)) {
                continue;
            }

            if (!isset($draft[$field]) || !self::hasDraftMetadataValue($field, $draft[$field])) {
                continue;
            }

            $updates[$field] = $draft[$field];
        }

        return $updates;
    }

    /**
     * Find an existing album matching the draft (release id, then artist + title).
     *
     * @param array $draft Normalized draft
     * @return array|null
     */
    public function findExisting($draft) {
        if (!empty($draft['discogs_release_id'])) {
            $hit = $this->collection->getAlbumByDiscogsReleaseId($draft['discogs_release_id']);
            if ($hit) {
                return $hit;
            }
        }

        $artistName = isset($draft['artist_name']) ? $draft['artist_name'] : '';
        $albumName = isset($draft['album_name']) ? $draft['album_name'] : '';

        return $this->collection->getAlbumByArtistAndName($artistName, $albumName);
    }

    /**
     * Normalize ownership flags to 0/1 ints for comparison and storage.
     *
     * @param array $row Album row or flag array
     * @return array{is_owned:int,want_to_own:int}
     */
    private static function normalizedFlagsFromRow($row) {
        return [
            'is_owned' => !empty($row['is_owned']) ? 1 : 0,
            'want_to_own' => !empty($row['want_to_own']) ? 1 : 0,
        ];
    }

    /**
     * Merge one mapped draft into the collection for the given import phase.
     *
     * @param array $draft Normalized album draft
     * @param string $phase collection|wantlist
     * @return string added|updated|skipped
     */
    public function processMappedItem(array $draft, $phase) {
        $artistName = isset($draft['artist_name']) ? trim((string) $draft['artist_name']) : '';
        $albumName = isset($draft['album_name']) ? trim((string) $draft['album_name']) : '';

        if ($artistName === '' || $albumName === '') {
            throw new Exception('Discogs draft is missing artist or album name');
        }

        $existing = $this->findExisting($draft);
        $flags = self::resolveFlags($phase, $existing);

        if ($existing === null) {
            $this->collection->addAlbum(
                $artistName,
                $albumName,
                isset($draft['release_year']) ? $draft['release_year'] : null,
                $flags['is_owned'],
                $flags['want_to_own'],
                isset($draft['cover_url']) ? $draft['cover_url'] : null,
                isset($draft['cover_images']) ? $draft['cover_images'] : null,
                !empty($draft['discogs_release_id']) ? $draft['discogs_release_id'] : null,
                isset($draft['style']) ? $draft['style'] : null,
                isset($draft['format']) ? $draft['format'] : null,
                isset($draft['artist_type']) ? $draft['artist_type'] : null,
                isset($draft['label']) ? $draft['label'] : null,
                isset($draft['producer']) ? $draft['producer'] : null
            );

            return 'added';
        }

        $metadataUpdates = self::shouldUpdateMetadata($existing, $draft);
        $currentFlags = self::normalizedFlagsFromRow($existing);
        $flagsChanged = $currentFlags['is_owned'] !== $flags['is_owned']
            || $currentFlags['want_to_own'] !== $flags['want_to_own'];

        if (!$flagsChanged && count($metadataUpdates) === 0) {
            return 'skipped';
        }

        $merged = $this->buildMergedAlbumRow($existing, $draft, $flags, $metadataUpdates);

        $this->collection->updateAlbum(
            $merged['id'],
            $merged['artist_name'],
            $merged['album_name'],
            $merged['release_year'],
            $merged['is_owned'],
            $merged['want_to_own'],
            $merged['cover_url'],
            $merged['cover_images'],
            $merged['discogs_release_id'],
            $merged['style'],
            $merged['format'],
            $merged['artist_type'],
            $merged['label'],
            $merged['producer']
        );

        return 'updated';
    }

    /**
     * Build full album field set for updateAlbum after merge rules.
     *
     * @param array $existing Existing row
     * @param array $draft Discogs draft
     * @param array $flags Resolved ownership flags
     * @param array $metadataUpdates Fields to fill from draft
     * @return array
     */
    private function buildMergedAlbumRow($existing, $draft, $flags, $metadataUpdates) {
        $row = [
            'id' => $existing['id'],
            'artist_name' => $existing['artist_name'],
            'album_name' => $existing['album_name'],
            'release_year' => isset($existing['release_year']) ? $existing['release_year'] : null,
            'is_owned' => $flags['is_owned'],
            'want_to_own' => $flags['want_to_own'],
            'cover_url' => isset($existing['cover_url']) ? $existing['cover_url'] : null,
            'cover_images' => isset($existing['cover_images']) && is_array($existing['cover_images'])
                ? $existing['cover_images']
                : [],
            'discogs_release_id' => isset($existing['discogs_release_id']) ? $existing['discogs_release_id'] : null,
            'style' => isset($existing['style']) ? $existing['style'] : null,
            'format' => isset($existing['format']) ? $existing['format'] : null,
            'artist_type' => isset($existing['artist_type']) ? $existing['artist_type'] : null,
            'label' => isset($existing['label']) ? $existing['label'] : null,
            'producer' => isset($existing['producer']) ? $existing['producer'] : null,
        ];

        foreach ($metadataUpdates as $field => $value) {
            $row[$field] = $value;
        }

        return $row;
    }

    /**
     * Process a page of mapped releases and aggregate counts.
     *
     * @param string $phase collection|wantlist
     * @param array $releases List of normalized drafts
     * @return array{counts:array,errors_sample:array}
     */
    public function processPage($phase, array $releases) {
        $counts = [
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $errors_sample = [];

        foreach ($releases as $draft) {
            if (!is_array($draft)) {
                $counts['errors']++;
                if (count($errors_sample) < 5) {
                    $errors_sample[] = 'Invalid release entry (not an array)';
                }
                continue;
            }

            try {
                $status = $this->processMappedItem($draft, $phase);
                if (isset($counts[$status])) {
                    $counts[$status]++;
                } else {
                    $counts['errors']++;
                    if (count($errors_sample) < 5) {
                        $errors_sample[] = 'Unexpected status: ' . $status;
                    }
                }
            } catch (Exception $e) {
                $counts['errors']++;
                if (count($errors_sample) < 5) {
                    $errors_sample[] = $e->getMessage();
                }
            }
        }

        return [
            'counts' => $counts,
            'errors_sample' => $errors_sample,
        ];
    }
}
