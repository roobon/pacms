<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8, part A: the full News module and Events (DATABASE-ARCHITECTURE.md §5, publishable
 * columns). Both use direct publishing with workflow, scheduling, revisions, builder content
 * and a per-item sidebar choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('published_at');
            // Sidebar: "default" = the module's sidebar (Settings), "none", or "custom" = the chosen global block.
            $table->string('sidebar_mode', 16)->default('default')->after('featured_media_id');
            $table->foreignId('sidebar_global_block_id')->nullable()->after('sidebar_mode')->constrained('global_blocks')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0)->after('author_id');
            $table->index(['status', 'publish_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('sidebar_mode', 16)->default('default');
            $table->foreignId('sidebar_global_block_id')->nullable()->constrained('global_blocks')->nullOnDelete();

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('venue')->nullable();
            $table->text('address')->nullable();
            $table->string('map_url', 1024)->nullable();
            $table->string('registration_url', 1024)->nullable();
            $table->string('organizer')->nullable();

            // Publishable columns (shared by every module).
            $table->string('status', 16)->default('draft');
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_at']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'publish_at']);
            $table->index(['featured', 'status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');

        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex(['status', 'publish_at']);
            $table->dropConstrainedForeignId('sidebar_global_block_id');
            $table->dropColumn(['publish_at', 'sidebar_mode', 'lock_version']);
        });
    }
};
