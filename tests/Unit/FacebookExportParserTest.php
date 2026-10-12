<?php

namespace Tests\Unit;

use App\Services\FacebookExportParser;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class FacebookExportParserTest extends TestCase
{
    protected string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDirectory = storage_path('framework/testing/parser_unit_test');
        if (File::exists($this->testDirectory)) {
            File::deleteDirectory($this->testDirectory);
        }
        File::makeDirectory($this->testDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testDirectory)) {
            File::deleteDirectory($this->testDirectory);
        }
        parent::tearDown();
    }

    public function test_validar_estructura_throws_when_no_recognized_files_exist(): void
    {
        file_put_contents($this->testDirectory.'/random_file.json', json_encode(['foo' => 'bar']));

        $parser = new FacebookExportParser($this->testDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no se encontraron archivos reconocidos');
        $parser->validarEstructura();
    }

    public function test_validar_estructura_throws_when_json_format_is_unrecognized(): void
    {
        file_put_contents($this->testDirectory.'/comments.json', json_encode([
            'unsupported_key' => 'unsupported_value',
        ]));

        $parser = new FacebookExportParser($this->testDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Formato JSON no reconocido en el archivo de comentarios');
        $parser->validarEstructura();
    }

    public function test_reacciones_omits_incomplete_reactions_without_defaulting_to_like(): void
    {
        file_put_contents($this->testDirectory.'/reactions.json', json_encode([
            'reactions_v2' => [
                [
                    'timestamp' => 1791500000,
                    'title' => 'A Juan le encanta una publicación',
                    'data' => [
                        ['reaction' => ['reaction' => 'LOVE']],
                    ],
                ],
                [
                    'timestamp' => 1791500001,
                    'title' => 'Reacción incompleta sin clave reaction',
                    'data' => [
                        ['reaction' => []],
                    ],
                ],
                [
                    'timestamp' => 1791500002,
                    'title' => 'Reacción incompleta con data vacía',
                    'data' => [],
                ],
                [
                    'timestamp' => 1791500003,
                    'title' => 'Reacción incompleta con tipo vacío',
                    'data' => [
                        ['reaction' => ['reaction' => '   ']],
                    ],
                ],
                [
                    'timestamp' => 1791500004,
                    'title' => 'Reacción de tipo WOW',
                    'data' => [
                        ['reaction' => 'WOW'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $reacciones = iterator_to_array($parser->reacciones());

        $this->assertCount(2, $reacciones);
        $this->assertSame('LOVE', $reacciones[0]['tipo']);
        $this->assertSame('WOW', $reacciones[1]['tipo']);

        // Asegurarse de que ninguno de los incompletos se asignó como LIKE
        foreach ($reacciones as $r) {
            $this->assertNotSame('LIKE', $r['tipo']);
        }
    }
}
