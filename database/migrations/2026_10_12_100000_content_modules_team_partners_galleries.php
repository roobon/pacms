<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8, part C.1: Team, Partners and Galleries (DATABASE-ARCHITECTURE.md §7), and
 * `content_relations`: links between content items chosen in relation fields (a project's
 * partners, manager and gallery; a gallery's event, project or program).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $this->common($table);
            $table->string('designation')->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('show_email')->default(false);
            $table->string('phone', 64)->nullable();
            $table->boolean('show_phone')->default(false);
            $table->json('social_links')->nullable();
            $table->unsignedInteger('position')->default(0);
            $this->publishable($table);
            $table->index(['status', 'position']);
        });

        Schema::create('partners', function (Blueprint $table) {
            $this->common($table);
            $table->string('website_url', 1024)->nullable();
            $table->unsignedInteger('position')->default(0);
            $this->publishable($table);
            $table->index(['status', 'position']);
        });

        Schema::create('galleries', function (Blueprint $table) {
            $this->common($table);
            $table->string('gallery_type', 16)->default('photo');
            $table->date('gallery_date')->nullable();
            $table->string('location')->nullable();
            $table->string('credit', 191)->nullable();
            $this->publishable($table);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            // Exactly one of media_id / video_url (checked by the service).
            $table->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->string('video_url', 1024)->nullable();
            $table->text('caption')->nullable();
            $table->string('alt_override')->nullable();
            $table->string('credit', 191)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['gallery_id', 'position']);
        });

        Schema::create('content_relations', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->string('field', 64);
            $table->string('related_type', 32);
            $table->unsignedBigInteger('related_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'field', 'position'], 'content_relations_owner_index');
            $table->index(['related_type', 'related_id'], 'content_relations_related_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_relations');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('team_members');
    }

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
