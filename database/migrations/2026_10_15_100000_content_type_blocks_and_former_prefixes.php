<?php

use App\Cms\Blocks\BlockRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8D.2: addresses a content type used before (redirected to its current address and
 * kept from pages), and the collection block of every existing admin-made type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_types', function (Blueprint $table) {
            $table->json('former_prefixes')->nullable()->after('route_prefix');
        });

        // Types made before 8D.2 get their "type/{key}" block row, so pages can store it.
        app(BlockRegistry::class)->syncContentTypes();
    }

    public function down(): void
    {
        Schema::table('content_types', function (Blueprint $table) {
            $table->dropColumn('former_prefixes');
        });
    }
};
