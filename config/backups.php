<?php

// ==========================================
// === BACKUP SYSTEM ===
// Configuración del Sistema de Respaldos
// ==========================================

return [
    'token' => env('BACKUP_MADRE_TOKEN'),
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),
    'pg_dump_path' => env('BACKUP_PG_DUMP_PATH'),
];
