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

    public function test_perfil_extracts_emails_from_profile_information(): void
    {
        file_put_contents($this->testDirectory.'/profile_information.json', json_encode([
            'profile_v2' => [
                'name' => [
                    'full_name' => 'Juana Rodriguez',
                ],
                'emails' => [
                    'emails' => [
                        'Juana.Rogj69@gmail.com',
                    ],
                    'previous_emails' => [],
                    'pending_emails' => [],
                    'ad_account_emails' => [
                        'juana.ads@gmail.com',
                    ],
                ],
                'profile_uri' => 'https://www.facebook.com/profile.php?id=61593018723655',
            ],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $perfil = $parser->perfil();

        $this->assertSame('Juana Rodriguez', $perfil['nombre']);
        $this->assertSame('https://www.facebook.com/profile.php?id=61593018723655', $perfil['uri']);
        $this->assertContains('juana.rogj69@gmail.com', $perfil['emails']);
        $this->assertContains('juana.ads@gmail.com', $perfil['emails']);
    }

    public function test_reacciones_extracts_new_meta_label_values_format(): void
    {
        file_put_contents($this->testDirectory.'/likes_and_reactions.json', json_encode([
            [
                'timestamp' => 1791298925,
                'label_values' => [
                    ['label' => 'Reacción', 'value' => 'Me gusta'],
                    [
                        'label' => 'URL',
                        'value' => 'https://www.facebook.com/61593018723655/posts/pfbid0123',
                        'href' => 'https://www.facebook.com/61593018723655/posts/pfbid0123',
                    ],
                    ['label' => 'Título', 'value' => ''],
                    ['label' => 'Nombre', 'value' => "Jorge 'Tico' Garcia"],
                ],
                'fbid' => '122101189879368972',
            ],
            [
                'timestamp' => 1791299000,
                'label_values' => [
                    ['label' => 'Reacción', 'value' => 'Me encanta'],
                    ['label' => 'Título', 'value' => 'Publicación destacada'],
                    ['label' => 'Nombre', 'value' => 'Autor Secundario'],
                ],
                'fbid' => '122101189879368973',
            ],
            [
                'timestamp' => 1791299100,
                'label_values' => [
                    ['label' => 'Reacción', 'value' => 'Me asombra'],
                    ['label' => 'Nombre', 'value' => 'Noticias Locales'],
                ],
                'fbid' => '122101189879368974',
            ],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $parser->validarEstructura();
        $reacciones = iterator_to_array($parser->reacciones());

        $this->assertCount(3, $reacciones);
        $this->assertSame('LIKE', $reacciones[0]['tipo']);
        $this->assertSame("Jorge 'Tico' Garcia", $reacciones[0]['objetivo']);
        $this->assertSame('https://www.facebook.com/61593018723655/posts/pfbid0123', $reacciones[0]['url']);

        $this->assertSame('LOVE', $reacciones[1]['tipo']);
        $this->assertSame('Publicación destacada', $reacciones[1]['objetivo']);

        $this->assertSame('WOW', $reacciones[2]['tipo']);
        $this->assertSame('Noticias Locales', $reacciones[2]['objetivo']);
    }

    public function test_identificador_extracts_numeric_id_from_facebook_subfolder_or_active_days(): void
    {
        $subfolder = $this->testDirectory.'/facebook-61593018723655-2026-10-06-0DJRGNSZ/your_facebook_activity/comments_and_reactions';
        File::makeDirectory($subfolder, 0755, true);

        file_put_contents($subfolder.'/your_comment_active_days.json', json_encode([
            [
                'timestamp' => 1791298925,
                'label_values' => [
                    ['label' => 'Recuento de días activos de comentarios en los últimos 28 días', 'value' => '3'],
                ],
                'fbid' => '61593018723655',
            ],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $parser->validarEstructura();

        $this->assertSame('id:61593018723655', $parser->identificador());
    }

    public function test_parser_deduplicates_items_across_multiple_daily_facebook_subfolders(): void
    {
        $day1 = $this->testDirectory.'/facebook-61593018723655-2026-10-05-AAA/your_facebook_activity/comments_and_reactions';
        $day2 = $this->testDirectory.'/facebook-61593018723655-2026-10-06-BBB/your_facebook_activity/comments_and_reactions';
        File::makeDirectory($day1, 0755, true);
        File::makeDirectory($day2, 0755, true);

        $sharedComment = [
            'timestamp' => 1791000000,
            'title' => 'Juana comentó una publicación.',
            'data' => [
                ['comment' => ['timestamp' => 1791000000, 'comment' => 'Excelente noticia']],
            ],
        ];
        $newComment = [
            'timestamp' => 1791086400,
            'title' => 'Juana comentó otra publicación.',
            'data' => [
                ['comment' => ['timestamp' => 1791086400, 'comment' => 'Comentario del día siguiente']],
            ],
        ];

        file_put_contents($day1.'/comments.json', json_encode(['comments_v2' => [$sharedComment]], JSON_THROW_ON_ERROR));
        file_put_contents($day2.'/comments.json', json_encode(['comments_v2' => [$sharedComment, $newComment]], JSON_THROW_ON_ERROR));

        $sharedReaction = [
            'timestamp' => 1791000100,
            'label_values' => [
                ['label' => 'Reacción', 'value' => 'Me gusta'],
                ['label' => 'Nombre', 'value' => 'Página Oficial'],
            ],
            'fbid' => '999001',
        ];
        file_put_contents($day1.'/likes_and_reactions.json', json_encode([$sharedReaction], JSON_THROW_ON_ERROR));
        file_put_contents($day2.'/likes_and_reactions.json', json_encode([$sharedReaction], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $comentarios = iterator_to_array($parser->comentarios());
        $reacciones = iterator_to_array($parser->reacciones());

        $this->assertCount(2, $comentarios);
        $this->assertCount(1, $reacciones);
    }

    public function test_validar_estructura_accepts_daily_export_with_auxiliary_files_and_facebook_id_folder_name(): void
    {
        file_put_contents($this->testDirectory.'/profile_update_history.json', json_encode([
            'profile_updates_v2' => [],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser(
            $this->testDirectory,
            'meta-2026-Oct-26-10-20-17/facebook-61594553723083-2026-10-28-KzM5cb1q'
        );

        $parser->validarEstructura();

        $this->assertSame('id:61594553723083', $parser->identificador());
    }

    public function test_parser_extracts_group_comments_attachment_comments_and_same_second_reactions(): void
    {
        file_put_contents($this->testDirectory.'/group_comments_1.json', json_encode([
            'group_comments_v2' => [
                [
                    'timestamp' => 1791300000,
                    'title' => 'Juana comentó en el grupo Vecinos Unidos.',
                    'data' => [
                        ['comment' => ['timestamp' => 1791300000, 'comment' => 'Presente en la reunión']],
                    ],
                ],
                [
                    'timestamp' => 1791300005,
                    'title' => 'Juana comentó en el grupo Vecinos Unidos.',
                    'attachments' => [
                        ['data' => [['media' => ['uri' => 'photos/sticker.png']]]],
                    ],
                    'data' => [
                        ['comment' => ['timestamp' => 1791300005, 'comment' => '']],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($this->testDirectory.'/likes_and_reactions.json', json_encode([
            [
                'timestamp' => 1791300100,
                'label_values' => [
                    ['label' => 'Reacción', 'value' => 'Me gusta'],
                    ['label' => 'Nombre', 'value' => 'Publicación Uno'],
                ],
                'fbid' => '111',
            ],
            [
                'timestamp' => 1791300100,
                'label_values' => [
                    ['label' => 'Reacción', 'value' => 'Me gusta'],
                    ['label' => 'Nombre', 'value' => 'Publicación Dos'],
                ],
                'fbid' => '222',
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($this->testDirectory.'/your_posts_in_groups.json', json_encode([
            [
                'timestamp' => 1791300200,
                'label_values' => [
                    ['label' => 'Publicación', 'value' => 'Aviso importante para la comunidad'],
                    ['label' => 'Grupo', 'value' => 'Vecinos Unidos'],
                ],
                'fbid' => '333',
            ],
        ], JSON_THROW_ON_ERROR));

        $parser = new FacebookExportParser($this->testDirectory);
        $parser->validarEstructura();

        $comentarios = iterator_to_array($parser->comentarios());
        $reacciones = iterator_to_array($parser->reacciones());
        $compartidos = iterator_to_array($parser->compartidos());

        $this->assertCount(2, $comentarios);
        $this->assertSame('Presente en la reunión', $comentarios[0]['texto']);
        $this->assertSame('[Comentario con imagen o sticker]', $comentarios[1]['texto']);

        $this->assertCount(2, $reacciones);
        $this->assertSame('Publicación Uno', $reacciones[0]['objetivo']);
        $this->assertSame('Publicación Dos', $reacciones[1]['objetivo']);

        $this->assertCount(1, $compartidos);
        $this->assertSame('Aviso importante para la comunidad', $compartidos[0]['descripcion']);
    }
}
