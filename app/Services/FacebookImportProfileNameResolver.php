<?php

namespace App\Services;

use App\Models\FacebookImportLog;
use App\Models\Profile;

class FacebookImportProfileNameResolver
{
    /**
     * Resolve display names for a page of imports without querying once per row.
     * Prefer the assigned profile name, then the name included in the export,
     * and finally a registered profile matching the export identifier.
     *
     * @param  iterable<FacebookImportLog>  $imports
     * @return array<int, string>
     */
    public function resolveMany(iterable $imports): array
    {
        $imports = collect($imports)->values();
        $identifiersByImport = [];
        $allIdentifiers = [];

        foreach ($imports as $import) {
            $facebookUser = $import->facebookUsers->first();
            $metadata = is_array($facebookUser?->metadata) ? $facebookUser->metadata : [];
            $identifiers = [];

            foreach ([$metadata['export_identifier'] ?? null, $metadata['profile_uri'] ?? null] as $candidate) {
                $identifier = FacebookProfileIdentifier::normalize(is_string($candidate) ? $candidate : null);
                if ($identifier !== null) {
                    $identifiers[] = $identifier;
                }
            }

            $importMetadata = is_array($import->metadata) ? $import->metadata : [];
            $candidatePaths = implode(' ', [$import->file_name, ...(is_array($importMetadata['files'] ?? null) ? $importMetadata['files'] : [])]);

            if ($identifiers === [] && preg_match('/facebook-(\d{5,})(?:[-_\/\\\\]|$)/i', $candidatePaths, $matches)) {
                $identifier = FacebookProfileIdentifier::normalize('id:'.$matches[1]);
                if ($identifier !== null) {
                    $identifiers[] = $identifier;
                }
            }

            $identifiersByImport[$import->id] = array_values(array_unique($identifiers));
            foreach ($identifiers as $identifier) {
                $allIdentifiers[] = $identifier;
            }
        }

        $registeredNames = collect();
        $allIdentifiers = array_values(array_unique($allIdentifiers));
        if ($allIdentifiers !== []) {
            $registeredNames = Profile::query()
                ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                ->whereIn('facebook_identifier', $allIdentifiers)
                ->get(['facebook_identifier', 'name'])
                ->groupBy('facebook_identifier')
                ->map(fn ($profiles) => $profiles->count() === 1 ? $profiles->first()->name : null);
        }

        $names = [];
        foreach ($imports as $import) {
            $facebookUser = $import->facebookUsers->first();
            $metadata = is_array($facebookUser?->metadata) ? $facebookUser->metadata : [];
            $exportName = is_string($metadata['name'] ?? null) ? trim($metadata['name']) : '';
            $name = $import->profile?->name ?: ($exportName !== '' ? $exportName : null);

            if ($name === null) {
                foreach ($identifiersByImport[$import->id] ?? [] as $identifier) {
                    $name = $registeredNames->get($identifier);
                    if (is_string($name) && trim($name) !== '') {
                        break;
                    }
                    $name = null;
                }
            }

            $names[$import->id] = $name ?: 'Nombre no incluido en la exportación';
        }

        return $names;
    }
}
