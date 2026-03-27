<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration - GESTIONES
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // ---------------------------------------------------------
        // 1. ENTORNO LOCAL (Desarrollo)
        // ---------------------------------------------------------
        'http://localhost:5173',
        'http://localhost:5176', // Tu puerto específico para Gestiones local
        'http://127.0.0.1:5176',

        // ---------------------------------------------------------
        // 2. ENTORNO PRODUCCIÓN (Ecosistema Yaman Kutx)
        // ---------------------------------------------------------
        'https://portal.yamankutx.com.gt',       // Indispensable para validar sesión con la Madre
        'https://api-portal.yamankutx.com.gt',   // Backend de la Madre para canje de tokens
        'https://gestiones.yamankutx.com.gt',    // La propia App
    ],

    'allowed_origins_patterns' => [
        // PATRÓN COMODÍN: Seguridad total para cualquier subdominio del ecosistema
        '#^https://.*\.yamankutx\.com\.gt$#',
        '#^https://yamankutx\.com\.gt$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // Cache de 24 horas para mejorar la velocidad de carga en el navegador

    // CRÍTICO: Permite el intercambio de cookies de autenticación (SSO)
    'supports_credentials' => true,

];
