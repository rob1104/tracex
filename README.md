# TraceX

TraceX es un sistema interno de gestión, auditoría y control de evidencias para operaciones de Marketing Digital. Permite a los equipos administrar un catálogo de cuentas de correo, vincular perfiles sociales a dichas cuentas, y llevar un registro detallado de evidencias (capturas de pantalla y enlaces) sobre la actividad que realiza cada colaborador en esos perfiles.

## 🚀 Características Principales

- **Gestión de Roles y Usuarios:** Diferentes niveles de acceso (`admin`, `cuentas`, `colaborador`, `user`), cada uno con permisos y vistas específicas.
- **Catálogo de Cuentas de Correo:** Control total del inventario de correos electrónicos.
- **Catálogo de Perfiles Sociales:** Vinculación de perfiles (Facebook, Instagram, LinkedIn, X, TikTok, YouTube) a las cuentas de correo.
- **Auditoría de Evidencias:** Registro de interacciones, comentarios y likes realizados por los perfiles sociales, incluyendo control de revisiones ("sospechosas", "aprobadas", etc.).
- **Captura Masiva por OCR (Inteligencia Artificial):** Integración con Google Cloud Vision API que permite al usuario pegar un bloque de imágenes/capturas. El sistema lee el texto, identifica automáticamente de qué perfil se trata y prepara las evidencias de manera masiva.
- **Asignaciones Masivas:** Transferencia en bloque de cuentas y perfiles entre los gestores y colaboradores de la agencia.
- **Exportación Profesional:** Generación de reportes detallados en formatos CSV (Excel) y PDF con soporte avanzado de filtros múltiples (fechas, estatus, red social, usuario, etc.).

---

## 🛠️ Stack Tecnológico

- **Framework Backend:** Laravel 11.x
- **Lenguaje:** PHP 8.3+
- **Frontend:** Livewire 3 + Alpine.js
- **Estilos:** Tailwind CSS v3 (Compilado vía Vite)
- **Base de Datos:** MySQL / MariaDB
- **Generación de PDFs:** `barryvdh/laravel-dompdf`
- **Integraciones:** Google Cloud Vision API

---

## ⚙️ Requisitos Previos

Antes de comenzar, asegúrate de tener instalado en tu entorno local:

- PHP >= 8.3
- Composer >= 2.x
- Node.js >= 18.x y npm
- Servidor de base de datos MySQL o MariaDB
- Git

---

## 💻 Instalación y Puesta en Marcha (Entorno Local)

Sigue estos pasos para levantar el proyecto en tu máquina y comenzar a desarrollar:

### 1. Clonar el repositorio
```bash
git clone https://github.com/rob1104/tracex.git
cd tracex
```

### 2. Instalar dependencias de PHP y Node
```bash
composer install
npm install
```

### 3. Configurar variables de entorno
Copia el archivo de ejemplo para crear tu propio `.env`:
```bash
cp .env.example .env
```
Genera la clave de la aplicación:
```bash
php artisan key:generate
```

Abre tu archivo `.env` y configura tus credenciales de base de datos:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tracex_local
DB_USERNAME=root
DB_PASSWORD=
```

Añade tu clave de Google Cloud Vision (Requerida para la función de captura masiva por OCR):
```env
GOOGLE_VISION_API_KEY=tu_api_key_aqui
```

### 4. Ejecutar migraciones y seeders
Esto creará las tablas necesarias y poblará la base de datos con información inicial (roles, usuarios de prueba o tipos de evidencia, según esté configurado):
```bash
php artisan migrate --seed
```

### 5. Compilar assets de frontend (Tailwind)
```bash
npm run dev
# (Para compilar para producción usa: npm run build)
```

### 6. Levantar el servidor de desarrollo local
Abre una nueva pestaña en tu terminal y ejecuta:
```bash
php artisan serve
```
El sistema estará disponible en `http://localhost:8000`.

---

## 🏗️ Flujo de Trabajo y Estructura del Código

- **Livewire Components:** La mayor parte de la lógica interactiva de la aplicación reside en el directorio `app/Livewire/`. Están agrupados por módulos (ej. `Admin/Emails`, `Admin/Profiles`, `User/EvidenceCreateMultiple`).
- **Vistas (Blade):** Las vistas correspondientes a cada componente Livewire se encuentran en `resources/views/livewire/`.
- **Plantillas de Reportes PDF:** Todo el diseño para la exportación de PDFs está aislado en `resources/views/pdf/` (ej. `evidences.blade.php`, `emails.blade.php`). Tienen estilos CSS crudos (`<style>`) compatibles con la librería DOMPDF.
- **Modelos y Base de Datos:** Los modelos de Eloquent (`EmailAccount`, `Profile`, `Evidence`, `EvidenceType`) se encuentran en `app/Models/`. Revisa las relaciones para entender cómo fluye la data (Un Usuario -> Múltiples Correos -> Múltiples Perfiles -> Múltiples Evidencias).

### 💡 Notas importantes para el equipo de desarrollo
- **Tailwind en Producción:** Si creas nuevos botones, alertas o elementos que usen clases de colores que no se hayan usado previamente (por ejemplo, `bg-emerald-600`), recuerda que necesitas ejecutar `npm run build` para que Vite escanee las vistas y las incluya en el CSS final, de lo contrario se verán transparentes en producción.
- **Soporte de PHP 8.3:** El servidor de producción está bloqueado en PHP 8.3. Se ha configurado el archivo `composer.json` (`config.platform.php: 8.3.0`) para prevenir la instalación accidental de dependencias incompatibles, incluso si desarrollas localmente usando PHP 8.4.

---

## 🚀 Despliegue a Producción (Plesk)

Para subir cambios al servidor de producción, realiza los siguientes pasos vía SSH:

```bash
# 1. Traer los últimos cambios
git pull

# 2. Instalar dependencias backend de forma segura
/opt/plesk/php/8.3/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader

# 3. Limpiar caché del framework
php artisan config:clear
php artisan view:clear
php artisan route:clear

# 4. Correr migraciones nuevas si las hay
php artisan migrate --force
```
