<?php

namespace App\Console\Commands;

use App\Cms\Exchange\SchemaGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes the machine-readable import/export schema for the core block types to
 * resources/schemas/pacms/1.0/ (CMS-BLOCK-SCHEMA.md). The admin download is generated
 * live, so it also includes this site's custom block types.
 */
class GenerateSchemaCommand extends Command
{
    protected $signature = 'pacms:schema';

    protected $description = 'Generate the PACMS JSON Schema and AI prompt helper files';

    public function handle(SchemaGenerator $schema): int
    {
        $dir = resource_path('schemas/pacms/'.SchemaGenerator::VERSION);
        File::ensureDirectoryExists($dir);

        File::put("{$dir}/document.schema.json", json_encode($schema->document(includeCustom: false), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        File::put("{$dir}/ai-prompt.txt", $schema->promptHelper(includeCustom: false)."\n");

        $this->components->info("Schema written to {$dir}.");

        return self::SUCCESS;
    }
}
