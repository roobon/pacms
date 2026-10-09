<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8, part C.2: Testimonials with their moderation log, Media Coverage (an archive of
 * press coverage with source checks) and the search index (DATABASE-ARCHITECTURE.md §7, §11).
 *
 * Media coverage links to programs and projects through `content_relations` (as projects,
 * programs and galleries do since 8C.1), so it has no program_id/project_id columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            // Internal only: never in a public response.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 191);
            $table->string('designation', 191)->nullable();
            $table->string('organization', 191)->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('citation')->nullable();
            $table->date('testimonial_date')->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('website_url', 1024)->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_version', 16)->nullable();
            $table->binary('submitted_ip', 16)->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index(['status', 'featured', 'position']);
            $table->index('user_id');
        });

        Schema::create('testimonial_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimonial_id')->constrained('testimonials')->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['testimonial_id', 'id']);
        });

        Schema::create('media_coverage', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('sidebar_mode', 16)->default('default');
            $table->foreignId('sidebar_global_block_id')->nullable()->constrained('global_blocks')->nullOnDelete();
            $table->string('source_name', 191)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('coverage_type', 16)->default('online');
            $table->date('publication_date')->nullable();
            $table->foreignId('archive_pdf_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('archive_video_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('archive_rights_confirmed')->default(false);
            $table->text('archive_rights_note')->nullable();
            // Source availability (CMS-ARCHITECTURE.md §19.1), written by the scheduled check.
            $table->string('availability', 16)->default('unknown');
            $table->string('availability_override', 16)->default('auto');
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('last_check_error', 512)->nullable();
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->timestamp('next_check_at')->nullable();
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
            $table->index(['status', 'published_at']);
            $table->index(['status', 'publish_at']);
            $table->index(['featured', 'status', 'published_at']);
            $table->index(['coverage_type', 'status', 'publication_date']);
            $table->index('next_check_at');
        });

        Schema::create('search_documents', function (Blueprint $table) {
            $table->id();
            // Morph alias + id of the indexed item; `type` is its registry key ("pages", "news"…) for filters.
            $table->string('searchable_type', 32);
            $table->unsignedBigInteger('searchable_id');
            $table->string('type', 32);
            $table->string('title', 512);
            $table->mediumText('body')->nullable();
            $table->string('url', 1024);
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('boost')->default(1);
            $table->timestamps();
            $table->unique(['searchable_type', 'searchable_id']);
            $table->index(['type', 'published_at']);
            $table->fullText(['title', 'body']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('media_coverage');
        Schema::dropIfExists('testimonial_moderation_logs');
        Schema::dropIfExists('testimonials');
    }
};
