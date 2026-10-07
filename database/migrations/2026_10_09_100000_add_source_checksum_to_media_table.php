<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate detection (Phase 7): images are re-encoded on upload, so the stored file's
 * checksum differs from the uploaded file. The checksum of the original upload lets a
 * second upload of the same file be recognised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->char('source_checksum', 64)->nullable()->after('checksum_sha256')->index();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['source_checksum']);
            $table->dropColumn('source_checksum');
        });
    }
};
