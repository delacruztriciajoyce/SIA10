<?php

function getRedisClient(): ?Redis
{
    if (!class_exists('Redis')) {
        return null;
    }

    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    } catch (Exception $ex) {
        return null;
    }
}

function getCachedRedisData(string $key): ?array
{
    $redis = getRedisClient();
    if ($redis === null) {
        return null;
    }

    $cached = $redis->get($key);
    if ($cached === false) {
        return null;
    }

    $decoded = json_decode($cached, true);
    return is_array($decoded) ? $decoded : null;
}

function setCachedRedisData(string $key, array $data, int $ttl = 3600): void
{
    $redis = getRedisClient();
    if ($redis === null) {
        return;
    }

    $redis->setex($key, $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
}