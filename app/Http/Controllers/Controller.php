<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Pollo Charly API',
    description: 'Documentación oficial interactiva de los endpoints RESTful del backend de Pollo Charly.',
    contact: new OA\Contact(
        name: 'Equipo de Desarrollo Pollo Charly'
    )
)]
#[OA\Server(
    url: '/',
    description: 'Servidor Actual (Relativo)'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Servidor Local (localhost:8000)'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor Local (127.0.0.1:8000)'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Ingrese su token de acceso Bearer generado en /api/login.'
)]
#[OA\Tag(
    name: 'Autenticación',
    description: 'Endpoints para inicio de sesión, información del usuario autenticado y cierre de sesión'
)]
#[OA\Tag(
    name: 'General',
    description: 'Endpoints generales de diagnóstico y verificación de salud de la API'
)]
abstract class Controller
{
    //
}
