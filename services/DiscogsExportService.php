<?php
class DiscogsExportService {
    /**
     * Partition local albums into export queues.
     *
     * @param array $albums Rows from MusicCollection::getAllAlbums()
     * @return array{collection: array, wantlist: array, missing_id: int}
     */
    public static function buildQueues(array $albums) {
        $collection = [];
        $wantlist = [];
        $missing = 0;
        foreach ($albums as $album) {
            if (!is_array($album)) {
                continue;
            }
            $owned = !empty($album['is_owned']);
            $want = !empty($album['want_to_own']);
            $rid = isset($album['discogs_release_id']) ? $album['discogs_release_id'] : null;
            $hasId = ($rid !== null && $rid !== '' && (int) $rid > 0);

            if ($owned || $want) {
                if (!$hasId) {
                    $missing++;
                    continue;
                }
            }
            if ($owned && $hasId) {
                $collection[] = $album;
                continue;
            }
            if ($want && !$owned && $hasId) {
                $wantlist[] = $album;
            }
        }
        return [
            'collection' => $collection,
            'wantlist' => $wantlist,
            'missing_id' => $missing,
        ];
    }

    /**
     * Process one batch of albums for a phase.
     *
     * @param string $phase collection|wantlist
     * @param array $albums Batch slice
     * @param array $existingIds Associative set of release ids already on Discogs for this phase
     * @param object $api DiscogsAPIService
     * @param string $username
     * @param array $instanceMap Release id => {folder_id, instance_id} for collection field writes
     * @return array{counts: array, errors_sample: array, existing_ids: array, instance_map: array}
     */
    public static function processBatch($phase, array $albums, array $existingIds, $api, $username, array $instanceMap = []) {
        $counts = [
            'added' => 0,
            'skipped' => 0,
            'missing_id' => 0,
            'errors' => 0,
            'fields_updated' => 0,
        ];
        $errorsSample = [];
        foreach ($albums as $album) {
            $rid = isset($album['discogs_release_id']) ? (int) $album['discogs_release_id'] : 0;
            if ($rid < 1) {
                $counts['missing_id']++;
                continue;
            }

            if (!empty($existingIds[$rid])) {
                $counts['skipped']++;
            } else {
                if ($phase === 'wantlist') {
                    $result = $api->addReleaseToWantlist($username, $rid);
                } else {
                    $result = $api->addReleaseToCollection($username, $rid);
                }
                $status = isset($result['status']) ? $result['status'] : 'error';
                if ($status === 'added') {
                    $counts['added']++;
                    $existingIds[$rid] = true;
                    if ($phase === 'collection') {
                        self::mergeInstanceFromAddResult($instanceMap, $rid, $result);
                    }
                } elseif ($status === 'skipped') {
                    $counts['skipped']++;
                    $existingIds[$rid] = true;
                } else {
                    $counts['errors']++;
                    if (count($errorsSample) < 10) {
                        $errorsSample[] = isset($result['message']) ? $result['message'] : 'Write failed';
                    }
                }
            }

            if ($phase === 'collection') {
                self::pushCollectionFieldsForAlbum($api, $username, $rid, $album, $instanceMap, $counts, $errorsSample);
            } elseif ($phase === 'wantlist') {
                self::pushWantlistNotesForAlbum($api, $username, $rid, $album, $counts, $errorsSample);
            }
        }
        return [
            'counts' => $counts,
            'errors_sample' => $errorsSample,
            'existing_ids' => $existingIds,
            'instance_map' => $instanceMap,
        ];
    }

    /**
     * Merge folder/instance ids from a collection add API result into the map.
     *
     * @param array $instanceMap Map keyed by release id (updated by reference)
     * @param int $releaseId Discogs release id
     * @param array $addResult Result from addReleaseToCollection
     */
    private static function mergeInstanceFromAddResult(array &$instanceMap, $releaseId, array $addResult) {
        if (isset($instanceMap[$releaseId])) {
            return;
        }
        $instanceId = isset($addResult['instance_id']) ? (int) $addResult['instance_id'] : 0;
        if ($instanceId < 1) {
            return;
        }
        $folderId = isset($addResult['folder_id']) ? (int) $addResult['folder_id'] : 1;
        $instanceMap[$releaseId] = [
            'folder_id' => $folderId,
            'instance_id' => $instanceId,
        ];
    }

    /**
     * Push local media/sleeve/notes to a collection instance when instance ids are known.
     *
     * @param object $api DiscogsAPIService
     * @param string $username Discogs username
     * @param int $releaseId Release id
     * @param array $album Local album row
     * @param array $instanceMap Instance map (by reference)
     * @param array $counts Counts (by reference)
     * @param array $errorsSample Error samples (by reference)
     */
    private static function pushCollectionFieldsForAlbum($api, $username, $releaseId, array $album, array &$instanceMap, array &$counts, array &$errorsSample) {
        if (empty($instanceMap[$releaseId])) {
            $counts['errors']++;
            if (count($errorsSample) < 10) {
                $errorsSample[] = 'missing instance for release ' . $releaseId;
            }
            return;
        }
        $meta = $instanceMap[$releaseId];
        $fields = self::localPersonalFieldsFromAlbum($album);
        $fieldResult = $api->updateCollectionInstanceFields(
            $username,
            $meta['folder_id'],
            $releaseId,
            $meta['instance_id'],
            $fields
        );
        self::recordFieldWriteResult($fieldResult, $counts, $errorsSample);
    }

    /**
     * Push local notes to a wantlist entry (empty string clears Discogs).
     *
     * @param object $api DiscogsAPIService
     * @param string $username Discogs username
     * @param int $releaseId Release id
     * @param array $album Local album row
     * @param array $counts Counts (by reference)
     * @param array $errorsSample Error samples (by reference)
     */
    private static function pushWantlistNotesForAlbum($api, $username, $releaseId, array $album, array &$counts, array &$errorsSample) {
        $fields = self::localPersonalFieldsFromAlbum($album);
        $fieldResult = $api->updateWantlistNotes($username, $releaseId, $fields['notes']);
        self::recordFieldWriteResult($fieldResult, $counts, $errorsSample);
    }

    /**
     * Trim local personal field values from an album export row.
     *
     * @param array $album Local album row
     * @return array{media_condition:string,sleeve_condition:string,notes:string}
     */
    private static function localPersonalFieldsFromAlbum(array $album) {
        return [
            'media_condition' => isset($album['media_condition']) ? trim((string) $album['media_condition']) : '',
            'sleeve_condition' => isset($album['sleeve_condition']) ? trim((string) $album['sleeve_condition']) : '',
            'notes' => isset($album['notes']) ? trim((string) $album['notes']) : '',
        ];
    }

    /**
     * Tally a field write API result into export counts.
     *
     * @param array $result API result with status key
     * @param array $counts Counts (by reference)
     * @param array $errorsSample Error samples (by reference)
     */
    private static function recordFieldWriteResult(array $result, array &$counts, array &$errorsSample) {
        $status = isset($result['status']) ? $result['status'] : 'error';
        if ($status === 'updated') {
            $counts['fields_updated']++;
            return;
        }
        $counts['errors']++;
        if (count($errorsSample) < 10) {
            $errorsSample[] = isset($result['message']) && $result['message'] !== ''
                ? $result['message']
                : 'Field update failed';
        }
    }
}
