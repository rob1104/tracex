<?php

namespace App\Services\GoogleDrive;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDriveClient
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $refreshToken;

    protected ?string $folderId;

    protected ?string $accessToken = null;

    /**
     * Create a new Google Drive client instance.
     */
    public function __construct(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $refreshToken = null,
        ?string $folderId = null
    ) {
        $this->clientId = $clientId ?? (string) config('services.google.drive.client_id');
        $this->clientSecret = $clientSecret ?? (string) config('services.google.drive.client_secret');
        $this->refreshToken = $refreshToken ?? (string) config('services.google.drive.refresh_token');
        $this->folderId = $folderId ?? config('services.google.drive.folder_id', 'root');
    }

    /**
     * Obtain a fresh OAuth 2.0 access token using the refresh token.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::error('Google Drive OAuth2 token refresh failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new Exception('Failed to obtain Google Drive access token: '.$response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /**
     * List Facebook export folders (matching 'meta-') in the configured or specified parent folder.
     *
     * @return array<int, array{id: string, name: string, createdTime?: string}>
     *
     * @throws Exception
     */
    public function listExportFolders(?string $parentFolderId = null): array
    {
        $targetFolder = $parentFolderId ?? $this->folderId ?? 'root';
        $token = $this->getAccessToken();

        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name contains 'meta-'";
        if ($targetFolder) {
            $query .= " and '{$targetFolder}' in parents";
        }

        $response = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $query,
            'fields' => 'files(id, name, createdTime)',
            'pageSize' => 100,
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to list export folders from Google Drive: '.$response->body());
        }

        return $response->json('files', []);
    }

    /**
     * Recursively list all JSON files under a Google Drive folder hierarchy, capturing their relative subpaths.
     *
     * @return array<int, array{id: string, name: string, size: ?int, relative_path: string}>
     *
     * @throws Exception
     */
    public function getFolderJsonFiles(string $folderId): array
    {
        $token = $this->getAccessToken();
        $queue = [['id' => $folderId, 'subpath' => '']];
        $jsonFiles = [];

        while (! empty($queue)) {
            $current = array_shift($queue);
            $currentFolder = $current['id'];
            $currentSubpath = $current['subpath'];

            $response = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
                'q' => "'{$currentFolder}' in parents and trashed = false",
                'fields' => 'files(id, name, mimeType, size)',
                'pageSize' => 100,
            ]);

            if (! $response->successful()) {
                throw new Exception("Failed to list contents of folder [{$currentFolder}] from Google Drive: ".$response->body());
            }

            $items = $response->json('files', []);
            foreach ($items as $item) {
                $itemSubpath = $currentSubpath !== '' ? $currentSubpath.'/'.$item['name'] : $item['name'];

                if ($item['mimeType'] === 'application/vnd.google-apps.folder') {
                    $queue[] = [
                        'id' => $item['id'],
                        'subpath' => $itemSubpath,
                    ];
                } elseif (str_ends_with(strtolower($item['name']), '.json') || ($item['mimeType'] ?? '') === 'application/json') {
                    $jsonFiles[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'size' => isset($item['size']) ? (int) $item['size'] : null,
                        'relative_path' => $itemSubpath,
                    ];
                }
            }
        }

        return $jsonFiles;
    }

    /**
     * Download all JSON files contained within an export folder into a local destination directory.
     * Preserves relative paths and verifies integrity for each downloaded file.
     *
     * @return array<string, int> Array mapping relative paths to downloaded file sizes in bytes.
     *
     * @throws Exception
     */
    public function downloadExportFolder(string $folderId, string $destinationDir): array
    {
        $jsonFiles = $this->getFolderJsonFiles($folderId);
        $downloaded = [];

        try {
            foreach ($jsonFiles as $file) {
                $destPath = rtrim($destinationDir, '/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['relative_path']);
                $actualSize = $this->downloadFile($file['id'], $destPath);

                if (! $this->verifyFileIntegrity($destPath, $actualSize)) {
                    throw new Exception("Integrity verification failed for downloaded file [{$file['relative_path']}]");
                }

                $downloaded[$file['relative_path']] = $actualSize;
            }
        } catch (Exception $e) {
            if (File::exists($destinationDir)) {
                File::deleteDirectory($destinationDir);
            }

            throw $e;
        }

        return $downloaded;
    }

    /**
     * List all JSON files inside the configured or specified Drive folder.
     *
     * @return array<int, array{id: string, name: string, size: ?int, mimeType: string}>
     *
     * @throws Exception
     */
    public function listFiles(?string $folderId = null): array
    {
        $targetFolder = $folderId ?? $this->folderId ?? 'root';
        $token = $this->getAccessToken();

        $query = "trashed = false and (mimeType = 'application/json' or name contains '.json')";
        if ($targetFolder) {
            $query .= " and '{$targetFolder}' in parents";
        }

        $response = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $query,
            'fields' => 'files(id, name, size, mimeType)',
            'pageSize' => 100,
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to list files from Google Drive: '.$response->body());
        }

        return $response->json('files', []);
    }

    /**
     * Download a file from Google Drive and save it to the specified local path.
     *
     * @throws Exception
     */
    public function downloadFile(string $fileId, string $destinationPath): int
    {
        $token = $this->getAccessToken();

        $dir = dirname($destinationPath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $response = Http::withToken($token)
            ->sink($destinationPath)
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'alt' => 'media',
            ]);

        if (! $response->successful()) {
            if (File::exists($destinationPath)) {
                File::delete($destinationPath);
            }

            throw new Exception("Failed to download file [{$fileId}] from Google Drive: ".$response->status());
        }

        return File::size($destinationPath);
    }

    /**
     * Verify that the downloaded file exists, is not empty, and contains valid JSON.
     */
    public function verifyFileIntegrity(string $filePath, ?int $expectedBytes = null): bool
    {
        if (! File::exists($filePath) || ! File::isReadable($filePath)) {
            return false;
        }

        $actualSize = File::size($filePath);
        if ($actualSize <= 0) {
            return false;
        }

        if ($expectedBytes !== null && $expectedBytes > 0 && $actualSize !== $expectedBytes) {
            return false;
        }

        $content = File::get($filePath);
        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Delete a file or folder permanently from Google Drive (Zero-Retention Rule).
     *
     * @throws Exception
     */
    public function deleteFile(string $fileId): bool
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");

        if ($response->status() === 204 || $response->successful()) {
            Log::info("Item permanently removed from Google Drive: {$fileId}");

            return true;
        }

        if ($response->status() === 404) {
            Log::warning("Item already deleted or not found in Google Drive: {$fileId}");

            return true;
        }

        Log::error("Failed to delete item from Google Drive: {$fileId}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new Exception("Google Drive API returned status {$response->status()} upon deleting item {$fileId}");
    }

    /**
     * Alias for deleteFile to explicitly indicate folder deletion.
     *
     * @throws Exception
     */
    public function deleteFolder(string $folderId): bool
    {
        return $this->deleteFile($folderId);
    }
}
