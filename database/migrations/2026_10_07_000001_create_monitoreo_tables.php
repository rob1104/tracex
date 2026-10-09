<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perfiles de Facebook monitoreados
        Schema::create('perfiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('alias');                           // "Sofía", "Mateo"
            $table->text('url_fb');                            // enlace al perfil que capturó el usuario (cifrado)
            $table->char('hash_url', 64)->unique();            // enlace normalizado + HMAC: empareja la primera carga
            $table->char('hash_fb', 64)->nullable()->unique(); // ID estable de la cuenta: se fija con la primera carga
            $table->timestamp('ultimo_export_en')->nullable();
            $table->timestamps();
        });

        // Cada carpeta meta-* que llega al Drive del proyecto = una importación (ej. una semana)
        Schema::create('importaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // null mientras no se asigne a un perfil
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('perfil_id')->nullable()->constrained('perfiles')->nullOnDelete();
            $table->string('drive_folder_id')->unique();       // evita importar dos veces la misma carpeta
            $table->string('nombre_carpeta');
            $table->char('hash_fb', 64)->nullable()->index();
            $table->char('hash_url', 64)->nullable()->index();
            $table->text('nombre_fb')->nullable();             // cifrado
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->json('totales')->nullable();
            $table->string('estado', 20)->default('descargando');
            // descargando | en_cola | sin_dueno | pendiente | asignado | duplicado | dividida | rechazado | error
            $table->text('error')->nullable();
            $table->timestamp('recibido_en')->nullable();
            $table->timestamp('borrado_drive_en')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('importacion_id')->constrained('importaciones')->cascadeOnDelete();
            $table->foreignUuid('perfil_id')->nullable()->constrained('perfiles')->cascadeOnDelete();
            $table->text('texto');                           // cifrado
            $table->text('contexto')->nullable();            // "comentó en la publicación de X" (cifrado)
            $table->dateTime('fecha');                       // hora de México
            $table->unsignedSmallInteger('longitud');
            $table->unsignedSmallInteger('num_palabras');
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            $table->unsignedTinyInteger('hora_local')
            ->storedAs("CAST(strftime('%H', fecha) AS INTEGER)");
            } else {
            $table->unsignedTinyInteger('hora_local')
            ->storedAs('HOUR(`fecha`)');
}
            $table->char('huella', 40);                      // evita duplicados entre semanas

            $table->index(['perfil_id', 'fecha']);
            $table->index(['perfil_id', 'hora_local']);
            // La base de datos garantiza que no haya duplicados:
            $table->unique(['importacion_id', 'huella']); // dentro de una misma carga
            $table->unique(['perfil_id', 'huella']);      // entre cargas del mismo perfil (los NULL de cargas pendientes no chocan)
        });

        Schema::create('reacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('importacion_id')->constrained('importaciones')->cascadeOnDelete();
            $table->foreignUuid('perfil_id')->nullable()->constrained('perfiles')->cascadeOnDelete();
            $table->string('tipo', 20);                      // me_gusta, me_encanta...
            $table->text('objetivo')->nullable();            // a qué reaccionó (cifrado)
            $table->dateTime('fecha');
            $table->char('huella', 40);

            $table->index(['perfil_id', 'fecha']);
            $table->index(['perfil_id', 'tipo']);
            // La base de datos garantiza que no haya duplicados:
            $table->unique(['importacion_id', 'huella']); // dentro de una misma carga
            $table->unique(['perfil_id', 'huella']);      // entre cargas del mismo perfil (los NULL de cargas pendientes no chocan)
        });

        Schema::create('compartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('importacion_id')->constrained('importaciones')->cascadeOnDelete();
            $table->foreignUuid('perfil_id')->nullable()->constrained('perfiles')->cascadeOnDelete();
            $table->text('descripcion')->nullable();         // cifrado
            $table->text('enlace')->nullable();              // cifrado
            $table->dateTime('fecha');
            $table->char('huella', 40);

            $table->index(['perfil_id', 'fecha']);
            // La base de datos garantiza que no haya duplicados:
            $table->unique(['importacion_id', 'huella']); // dentro de una misma carga
            $table->unique(['perfil_id', 'huella']);      // entre cargas del mismo perfil (los NULL de cargas pendientes no chocan)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compartidos');
        Schema::dropIfExists('reacciones');
        Schema::dropIfExists('comentarios');
        Schema::dropIfExists('importaciones');
        Schema::dropIfExists('perfiles');
    }
};
