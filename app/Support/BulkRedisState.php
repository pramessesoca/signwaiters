<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;

class BulkRedisState
{
    public const FINAL_TTL_SECONDS = 86400;

    public static function uploadKey(string $id): string
    {
        return 'bulk:upload:'.$id;
    }

    public static function downloadKey(string $id): string
    {
        return 'bulk:download:'.$id;
    }

    public static function uploadIndexKey(): string
    {
        return 'bulk:upload:index';
    }

    public static function downloadIndexKey(): string
    {
        return 'bulk:download:index';
    }

    public static function setUpload(string $id, array $state, ?int $ttlSeconds = null): void
    {
        self::set(self::uploadKey($id), $state, self::uploadIndexKey(), $id, $ttlSeconds);
    }

    public static function setDownload(string $id, array $state, ?int $ttlSeconds = null): void
    {
        self::set(self::downloadKey($id), $state, self::downloadIndexKey(), $id, $ttlSeconds);
    }

    public static function getUpload(string $id): ?array
    {
        return self::get(self::uploadKey($id));
    }

    public static function getDownload(string $id): ?array
    {
        return self::get(self::downloadKey($id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function clearUploadDoneFailed(): array
    {
        return self::clearByStatus(self::uploadIndexKey(), 'upload', ['done', 'failed']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function clearDownloadDoneFailed(): array
    {
        return self::clearByStatus(self::downloadIndexKey(), 'download', ['done', 'failed']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function clearAllUpload(): array
    {
        return self::clearByStatus(self::uploadIndexKey(), 'upload', null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function clearAllDownload(): array
    {
        return self::clearByStatus(self::downloadIndexKey(), 'download', null);
    }

    private static function set(string $key, array $state, string $indexKey, string $id, ?int $ttlSeconds): void
    {
        $payload = json_encode($state, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        $conn = Redis::connection('default');
        $conn->set($key, $payload);
        $conn->sadd($indexKey, $id);

        if ($ttlSeconds !== null) {
            $conn->expire($key, $ttlSeconds);
        }
    }

    private static function get(string $key): ?array
    {
        $raw = Redis::connection('default')->get($key);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, string>|null  $statuses
     * @return array<int, array<string, mixed>>
     */
    private static function clearByStatus(string $indexKey, string $type, ?array $statuses): array
    {
        $conn = Redis::connection('default');
        $ids = $conn->smembers($indexKey) ?? [];
        $deletedStates = [];

        foreach ($ids as $id) {
            if (! is_string($id) || $id === '') {
                continue;
            }

            $key = $type === 'upload' ? self::uploadKey($id) : self::downloadKey($id);
            $state = self::get($key);

            if ($state === null) {
                $conn->srem($indexKey, $id);
                continue;
            }

            if ($statuses !== null) {
                $status = (string) ($state['status'] ?? '');
                if (! in_array($status, $statuses, true)) {
                    continue;
                }
            }

            $deletedStates[] = $state;
            $conn->del($key);
            $conn->srem($indexKey, $id);
        }

        return $deletedStates;
    }
}

