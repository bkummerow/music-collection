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
     * @return array{counts: array, errors_sample: array, existing_ids: array}
     */
    public static function processBatch($phase, array $albums, array $existingIds, $api, $username) {
        $counts = ['added' => 0, 'skipped' => 0, 'missing_id' => 0, 'errors' => 0];
        $errorsSample = [];
        foreach ($albums as $album) {
            $rid = isset($album['discogs_release_id']) ? (int) $album['discogs_release_id'] : 0;
            if ($rid < 1) {
                $counts['missing_id']++;
                continue;
            }
            if (!empty($existingIds[$rid])) {
                $counts['skipped']++;
                continue;
            }
            if ($phase === 'wantlist') {
                $result = $api->addReleaseToWantlist($username, $rid);
            } else {
                $result = $api->addReleaseToCollection($username, $rid);
            }
            $status = isset($result['status']) ? $result['status'] : 'error';
            if ($status === 'added') {
                $counts['added']++;
                $existingIds[$rid] = true;
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
        return [
            'counts' => $counts,
            'errors_sample' => $errorsSample,
            'existing_ids' => $existingIds,
        ];
    }
}
