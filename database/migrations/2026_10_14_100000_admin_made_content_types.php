<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8D: content types made in the admin (Design → Content types). `content_types`
 * holds each type's definition (names, URL prefix, options, fields built with the field
 * builder); all their items share `custom_items`, with the type's own field values in a
 * JSON column. Built-in modules keep their own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_types', function (Blueprint $table) {
            $table->id();
            // Registry key and permission prefix ("success_stories"); fixed once created.
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('singular', 120);
            $table->string('icon', 64)->default('bi-collection');
            $table->string('route_prefix', 64)->unique();
            // editorial = the News workflow; managed = active / inactive with one permission.
            $table->string('workflow', 16)->default('editorial');
            $table->json('fields')->nullable();
            // field key => details | section | hidden
            $table->json('display')->nullable();
            $table->boolean('has_archive')->default(true);
            $table->boolean('searchable')->default(true);
            $table->boolean('has_categories')->default(false);
            $table->boolean('has_documents')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('custom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_type_id')->constrained('content_types')->restrictOnDelete();
            $table->string('title');
            $table->string('slug', 191);
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('sidebar_mode', 16)->default('default');
            $table->foreignId('sidebar_global_block_id')->nullable()->constrained('global_blocks')->nullOnDelete();
            // The type's own fields: key => value.
            $table->json('fields')->nullable();
            $table->unsignedInteger('position')->default(0);
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
            $table->unique(['content_type_id', 'slug']);
            $table->index(['content_type_id', 'status', 'published_at']);
            $table->index(['content_type_id', 'status', 'publish_at']);
            $table->index(['content_type_id', 'featured', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_items');
        Schema::dropIfExists('content_types');
    }
};
