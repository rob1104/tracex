<?php

namespace App\Exceptions;

use RuntimeException;

/** El usuario revocó el acceso o el token caducó: hay que volver a conectar Google Drive. */
class GoogleDesconectado extends RuntimeException {}
