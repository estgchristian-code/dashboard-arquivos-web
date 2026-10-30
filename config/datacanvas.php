<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Armazenamento das importações
    |--------------------------------------------------------------------------
    |
    | Os arquivos e as prévias ficam em um disco privado, fora de public/.
    | Nenhuma URL pública é gerada para esses arquivos.
    |
    */

    'disk' => 'imports',

    'retention_hours' => (int) env('DATACANVAS_IMPORT_RETENTION_HOURS', 24),

    'preview_rows' => (int) env('DATACANVAS_PREVIEW_ROWS', 25),

    /*
    |--------------------------------------------------------------------------
    | Limites de processamento
    |--------------------------------------------------------------------------
    |
    | Os limites são aplicados no servidor. O limite de células é uma
    | proteção adicional e pode ser atingido antes dos limites de linhas
    | ou colunas em arquivos muito largos.
    |
    */

    'limits' => [
        'max_file_bytes' => (int) env('DATACANVAS_MAX_FILE_BYTES', 10 * 1024 * 1024),
        'max_rows' => (int) env('DATACANVAS_MAX_ROWS', 10000),
        'max_columns' => (int) env('DATACANVAS_MAX_COLUMNS', 100),
        'max_cells' => (int) env('DATACANVAS_MAX_CELLS', 200000),
        'max_processing_seconds' => (int) env('DATACANVAS_MAX_PROCESSING_SECONDS', 20),
        'max_header_length' => 120,
        'max_zip_entries' => 1000,
        'max_zip_entry_bytes' => 50 * 1024 * 1024,
        'max_zip_uncompressed_bytes' => 100 * 1024 * 1024,
        'max_zip_compression_ratio' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting para visitantes anônimos
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'enabled' => filter_var(env('DATACANVAS_RATE_LIMIT_ENABLED', true), FILTER_VALIDATE_BOOL),
        'per_minute' => (int) env('DATACANVAS_RATE_LIMIT_PER_MINUTE', 5),
        'per_hour' => (int) env('DATACANVAS_RATE_LIMIT_PER_HOUR', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limpeza de uploads temporários do Livewire
    |--------------------------------------------------------------------------
    */

    'livewire_temp_retention_hours' => (int) env('DATACANVAS_LIVEWIRE_TEMP_RETENTION_HOURS', 24),

];
