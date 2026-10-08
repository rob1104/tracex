<?php

namespace Tests\Feature;

use App\Services\GoogleDrive\GoogleDriveClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveClientTest extends TestCase
{
    protected string $testStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStorageDir = storage_path('framework/testing/drive_test');
        if (File::exists($this->testStorageDir)) {
            File::deleteDirectory($this->testStorageDir);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testStorageDir)) {
            File::deleteDirectory($this->testStorageDir);
        }
        parent::tearDown();
    }

    public function test_it_obtains_and_caches_access_token(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_access_token_123',
                'expires_in' => 3600,
            ], 200),
        ]);

        $client = new GoogleDriveClient('mock_client_id', 'mock_secret', 'mock_refresh_token');

        $token1 = $client->getAccessToken();
        $token2 = $client->getAccessToken();

        $this->assertSame('mock_access_token_123', $token1);
        $this->assertSame('mock_access_token_123', $token2);
        Http::assertSentCount(1);
    }

    public function test_it_lists_export_folders(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock_token'], 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    [
                        'id' => 'folder_123',
                        'name' => 'meta-2026-Oct-06-11-05-44',
                        'createdTime' => '2026-10-06T17:11:17.747Z',
                    ],
                ],
            ], 200),
        ]);

        $client = new GoogleDriveClient('mock_client_id', 'mock_secret', 'mock_refresh_token', 'root');
        $folders = $client->listExportFolders();

        $this->assertCount(1, $folders);
        $this->assertSame('folder_123', $folders[0]['id']);
        $this->assertSame('meta-2026-Oct-06-11-05-44', $folders[0]['name']);
    }

    public function test_it_gets_folder_json_files_hierarchically(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock_token'], 200),
            'https://www.googleapis.com/drive/v3/files*' => function (Request $request) {
                $query = (string) $request->data()['q'];
                if (str_contains($query, "'root_meta' in parents")) {
                    return Http::response([
                        'files' => [
                            [
                                'id' => 'subfolder_1',
                                'name' => 'facebook-user-123',
                                'mimeType' => 'application/vnd.google-apps.folder',
                            ],
                        ],
                    ], 200);
                }

                if (str_contains($query, "'subfolder_1' in parents")) {
                    return Http::response([
                        'files' => [
                            [
                                'id' => 'file_profile',
                                'name' => 'profile_information.json',
                                'mimeType' => 'application/json',
                                'size' => 1024,
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['files' => []], 200);
            },
        ]);

        $client = new GoogleDriveClient('mock_client_id', 'mock_secret', 'mock_refresh_token');
        $files = $client->getFolderJsonFiles('root_meta');

        $this->assertCount(1, $files);
        $this->assertSame('file_profile', $files[0]['id']);
        $this->assertSame('facebook-user-123/profile_information.json', $files[0]['relative_path']);
    }

    public function test_it_downloads_and_verifies_export_folder(): void
    {
        $jsonPayload = json_encode(['foo' => 'bar']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock_token'], 200),
            'https://www.googleapis.com/drive/v3/files/file_test*' => Http::response($jsonPayload, 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    [
                        'id' => 'file_test',
                        'name' => 'comments.json',
                        'mimeType' => 'application/json',
                        'size' => strlen($jsonPayload),
                    ],
                ],
            ], 200),
        ]);

        $client = new GoogleDriveClient('mock_client_id', 'mock_secret', 'mock_refresh_token');
        $downloaded = $client->downloadExportFolder('folder_meta', $this->testStorageDir);

        $this->assertArrayHasKey('comments.json', $downloaded);
        $this->assertFileExists($this->testStorageDir.'/comments.json');
        $this->assertJsonStringEqualsJsonString($jsonPayload, File::get($this->testStorageDir.'/comments.json'));
    }

    public function test_it_deletes_file_or_folder_from_google_drive(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock_token'], 200),
            'https://www.googleapis.com/drive/v3/files/folder_to_delete' => Http::response('', 204),
        ]);

        $client = new GoogleDriveClient('mock_client_id', 'mock_secret', 'mock_refresh_token');
        $deleted = $client->deleteFolder('folder_to_delete');

        $this->assertTrue($deleted);
    }
}
