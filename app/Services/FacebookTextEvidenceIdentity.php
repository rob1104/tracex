<?php

namespace App\Services;

use App\Models\Evidence;

class FacebookTextEvidenceIdentity
{
    /**
     * @param  array<string, mixed>  $record
     */
    public function fingerprint(int $profileId, int $typeId, array $record): string
    {
        return hash('sha256', implode("\0", [
            $profileId,
            $typeId,
            $record['dedupe_text'] ?? $record['text'],
            $record['occurred_at'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function findExisting(int $profileId, int $typeId, array $record): ?Evidence
    {
        $fingerprint = $this->fingerprint($profileId, $typeId, $record);

        return Evidence::query()
            ->with(['user', 'evidenceType'])
            ->where('source_fingerprint', $fingerprint)
            ->first()
            ?? Evidence::query()
                ->with(['user', 'evidenceType'])
                ->where('profile_id', $profileId)
                ->where('evidence_type_id', $typeId)
                ->where('comment', $record['text'])
                ->where('external_published_at', $record['occurred_at'])
                ->first();
    }

    public function forcedFingerprint(string $fingerprint, int $importId, int $sourceLine): string
    {
        return hash('sha256', implode("\0", [
            'facebook_txt_duplicate',
            $fingerprint,
            $importId,
            $sourceLine,
        ]));
    }
}
