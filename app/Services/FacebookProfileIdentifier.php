<?php

namespace App\Services;

class FacebookProfileIdentifier
{
    public static function normalize(?string $identifier): ?string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        if (preg_match('/^id:(\d+)$/i', $identifier, $matches) || preg_match('/^(\d+)$/', $identifier, $matches)) {
            return 'id:'.$matches[1];
        }

        if (preg_match('/^registro:(\d+)$/i', $identifier, $matches)) {
            return 'registro:'.$matches[1];
        }

        if (preg_match('/^uri:(.+)$/i', $identifier, $matches)) {
            $identifier = trim($matches[1]);
        }

        $parts = parse_url($identifier);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return null;
        }

        $host = preg_replace('/^(www|m|web)\./', '', $host);

        if ($host !== 'facebook.com' && ! str_ends_with($host, '.facebook.com')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        if (isset($query['id']) && preg_match('/^\d+$/', (string) $query['id'])) {
            return 'id:'.$query['id'];
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($path === '') {
            return null;
        }

        return 'uri:https://facebook.com/'.mb_strtolower($path, 'UTF-8');
    }
}
