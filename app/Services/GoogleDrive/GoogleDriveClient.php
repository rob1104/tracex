<?php

namespace App\Services\GoogleDrive;

use Exception;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleDriveClient
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $refreshToken;

    protected ?string $folderId;

    protected ?GoogleClient $googleClient = null;

    protected ?GoogleDriveService $driveService = null;

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
     * Return the current OAuth access token, refreshing it when needed.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        $client = $this->googleClient();

        if ($client->isAccessTokenExpired()) {
            $token = $client->fetchAccessTokenWithRefreshToken($this->refreshToken);
            if (isset($token['error'])) {
                $description = $token['error_description'] ?? $token['error'];
                throw new Exception('Google Drive OAuth token refresh failed: '.$description);
            }

            $client->setAccessToken($token);
        }

        $token = $client->getAccessToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new Exception('Google Drive did not return an access token.');
        }

        return $accessToken;
    }

    /**
     * @return array<int, array{id: string, name: string, createdTime?: string}>
     *
     * @throws Exception
     */
    public function listExportFolders(?string $parentFolderId = null): array
    {
        $targetFolder = $parentFolderId ?? $this->folderId ?? 'root';
        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name contains 'meta-'";

        if ($targetFolder !== null && $targetFolder !== '') {
            $query .= " and '{$targetFolder}' in parents";
        }

        $folders = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'fields' => 'nextPageToken,files(id,name,createdTime)',
                'pageSize' => 100,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->driveService()->files->listFiles($params);

            foreach ($response->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'createdTime' => $file->getCreatedTime(),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $folders;
    }

    /**
     * @return array<int, array{id: string, name: string, size: ?int, relative_path: string}>
     *
     * @throws Exception
     */
    public function getFolderJsonFiles(string $folderId): array
    {
        $queue = [['id' => $folderId, 'subpath' => '']];
        $jsonFiles = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $pageToken = null;

            do {
                $params = [
                    'q' => "'{$current['id']}' in parents and trashed = false",
                    'fields' => 'nextPageToken,files(id,name,mimeType,size)',
                    'pageSize' => 100,
                    'supportsAllDrives' => true,
                    'includeItemsFromAllDrives' => true,
                ];

                if ($pageToken !== null) {
                    $params['pageToken'] = $pageToken;
                }

                $response = $this->driveService()->files->listFiles($params);

                foreach ($response->getFiles() as $item) {
                    $itemSubpath = $current['subpath'] !== ''
                        ? $current['subpath'].'/'.$item->getName()
                        : $item->getName();

                    if ($item->getMimeType() === 'application/vnd.google-apps.folder') {
                        $queue[] = [
                            'id' => $item->getId(),
                            'subpath' => $itemSubpath,
                        ];
                    } elseif (
                        str_ends_with(strtolower($item->getName()), '.json')
                        || $item->getMimeType() === 'application/json'
                    ) {
                        $jsonFiles[] = [
                            'id' => $item->getId(),
                            'name' => $item->getName(),
                            'size' => $item->getSize() === null ? null : (int) $item->getSize(),
                            'relative_path' => $itemSubpath,
                        ];
                    }
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken !== null);
        }

        return $jsonFiles;
    }

    /**
     * @return array<string, int>
     *
     * @throws Exception
     */
    public function downloadExportFolder(string $folderId, string $destinationDir): array
    {
        $jsonFiles = $this->getFolderJsonFiles($folderId);
        $downloaded = [];

        try {
            foreach ($jsonFiles as $file) {
                $destinationPath = rtrim($destinationDir, '/\\')
                    .DIRECTORY_SEPARATOR
                    .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['relative_path']);
                $actualSize = $this->downloadFile($file['id'], $destinationPath);

                if (! $this->verifyFileIntegrity($destinationPath, $actualSize)) {
                    throw new Exception("Integrity verification failed for downloaded file [{$file['relative_path']}]");
                }

                $downloaded[$file['relative_path']] = $actualSize;
            }
        } catch (Throwable $exception) {
            if (File::exists($destinationDir)) {
                File::deleteDirectory($destinationDir);
            }

            throw new Exception('Failed to download the Google Drive export folder.', previous: $exception);
        }

        return $downloaded;
    }

    /**
     * @return array<int, array{id: string, name: string, size: ?int, mimeType: string}>
     *
     * @throws Exception
     */
    public function listFiles(?string $folderId = null): array
    {
        $targetFolder = $folderId ?? $this->folderId ?? 'root';
        $query = "trashed = false and (mimeType = 'application/json' or name contains '.json')";

        if ($targetFolder !== null && $targetFolder !== '') {
            $query .= " and '{$targetFolder}' in parents";
        }

        $files = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'fields' => 'nextPageToken,files(id,name,size,mimeType)',
                'pageSize' => 100,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->driveService()->files->listFiles($params);

            foreach ($response->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'size' => $file->getSize() === null ? null : (int) $file->getSize(),
                    'mimeType' => $file->getMimeType(),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $files;
    }

    /**
     * Download a file to disk without loading the export into memory.
     *
     * @throws Exception
     */
    public function downloadFile(string $fileId, string $destinationPath): int
    {
        $this->getAccessToken();

        $directory = dirname($destinationPath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        try {
            $response = $this->googleClient()->authorize()->get(
                'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId),
                [
                    'query' => ['alt' => 'media'],
                    'sink' => $destinationPath,
                ]
            );
        } catch (Throwable $exception) {
            if (File::exists($destinationPath)) {
                File::delete($destinationPath);
            }

            throw new Exception("Failed to download file [{$fileId}] from Google Drive.", previous: $exception);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            File::delete($destinationPath);

            throw new Exception("Google Drive returned status {$response->getStatusCode()} while downloading file [{$fileId}].");
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

        return json_validate(File::get($filePath));
    }

    /**
     * Permanently delete a file or folder from Google Drive.
     *
     * @throws Exception
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->driveService()->files->delete($fileId, ['supportsAllDrives' => true]);

            Log::info("Item permanently removed from Google Drive: {$fileId}");

            return true;
        } catch (Throwable $exception) {
            $code = method_exists($exception, 'getCode') ? (int) $exception->getCode() : 0;

            if ($code === 404) {
                Log::warning("Item already deleted or not found in Google Drive: {$fileId}");

                return true;
            }

            Log::error("Failed to delete item from Google Drive: {$fileId}", [
                'error' => $exception->getMessage(),
                'code' => $code,
            ]);

            throw new Exception("Google Drive failed to delete item {$fileId}.", previous: $exception);
        }
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

    /**
     * @throws Exception
     */
    protected function googleClient(): GoogleClient
    {
        if ($this->googleClient !== null) {
            return $this->googleClient;
        }

        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new Exception('Google Drive OAuth credentials are not configured.');
        }

        $client = new GoogleClient;
        $client->setApplicationName(config('app.name', 'TraceX'));
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri((string) config('services.google.drive.redirect_uri'));
        $client->setAccessType('offline');
        $client->addScope(GoogleDriveService::DRIVE);

        $token = $client->fetchAccessTokenWithRefreshToken($this->refreshToken);
        if (isset($token['error'])) {
            $description = $token['error_description'] ?? $token['error'];
            throw new Exception('Google Drive OAuth token refresh failed: '.$description);
        }

        $client->setAccessToken($token);
        $this->googleClient = $client;

        return $client;
    }

    /**
     * @throws Exception
     */
    protected function driveService(): GoogleDriveService
    {
        $this->getAccessToken();

        return $this->driveService ??= new GoogleDriveService($this->googleClient());
    }
}
