<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8, part B: Projects, Programs and Publications (DATABASE-ARCHITECTURE.md §7) on the
 * content engine, and `attachments` (documents listed on projects and programs).
 *
 * Partners, the project manager as a team member and galleries link to modules built in
 * part 8C; their columns and pivot tables are added there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $this->common($table);
            $table->string('project_status', 16)->default('ongoing');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->string('manager_name', 191)->nullable();
            $table->string('website_url', 1024)->nullable();
            $this->publishable($table);
            $table->index(['status', 'project_status']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $this->common($table);
            // Ordered lists that are only ever displayed (repeaters): [{text}] and [{title, text}].
            $table->json('objectives')->nullable();
            $table->json('activities')->nullable();
            $this->publishable($table);
        });

        Schema::create('publications', function (Blueprint $table) {
            $this->common($table);
            $table->date('publication_date')->nullable();
            $table->string('author_text')->nullable();
            $table->foreignId('document_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('external_url', 1024)->nullable();
            $this->publishable($table);
            $table->index(['status', 'publication_date']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('attachable_type', 32);
            $table->unsignedBigInteger('attachable_id');
            $table->string('label')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('projects');
    }

    /** Columns every content module shares (title, text, image, sidebar). */
    private function common(Blueprint $table): void
    {
        $table->id();
        $table->string('title');
        $table->string('slug', 191)->unique();
        $table->text('excerpt')->nullable();
        $table->longText('body')->nullable();
        $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
        $table->string('sidebar_mode', 16)->default('default');
        $table->foreignId('sidebar_global_block_id')->nullable()->constrained('global_blocks')->nullOnDelete();
    }

    /** Workflow, scheduling and authorship columns. */
    private function publishable(Blueprint $table): void
    {
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
    }
};
