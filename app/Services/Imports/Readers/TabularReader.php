<?php

namespace App\Services\Imports\Readers;

use App\Enums\ImportFormat;
use App\Services\Imports\Data\ParsedTable;

interface TabularReader
{
    public function read(string $path, ImportFormat $format): ParsedTable;
}
