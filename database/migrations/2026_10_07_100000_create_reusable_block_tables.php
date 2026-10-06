<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: global blocks, block templates and custom block types (CMS-ARCHITECTURE.md §11–12).
 * All three own block trees in `blocks` (owner morph), like pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->string('kind', 16)->default('generic'); // generic now; header/footer in Phase 9
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->boolean('has_unpublished_changes')->default(true);
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'status']);
        });

        Schema::create('block_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('scope', 16)->default('section'); // block | section | page
            $table->string('category', 64)->nullable();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 16)->default('published'); // published = offered in the palette
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scope']);
        });

        Schema::table('block_types', function (Blueprint $table) {
            $table->boolean('has_unpublished_changes')->default(false)->after('version');
            $table->foreignId('published_revision_id')->nullable()->after('has_unpublished_changes')->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_revision_id');
            $table->unsignedInteger('lock_version')->default(0)->after('published_at');
            $table->foreignId('created_by')->nullable()->after('lock_version')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('blocks', function (Blueprint $table) {
            // Only set on global-ref blocks; RESTRICT keeps an in-use global block from vanishing.
            $table->foreignId('global_block_id')->nullable()->after('block_type_id')->constrained('global_blocks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('global_block_id');
        });

        Schema::table('block_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_revision_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['has_unpublished_changes', 'published_at', 'lock_version', 'deleted_at']);
        });

        Schema::dropIfExists('block_templates');
        Schema::dropIfExists('global_blocks');
    }
};
