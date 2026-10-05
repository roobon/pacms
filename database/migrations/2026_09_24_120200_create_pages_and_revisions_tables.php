<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type', 32);
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedInteger('number');
            $table->string('kind', 16);
            $table->json('snapshot');
            $table->string('schema_version', 8);
            $table->string('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['revisionable_type', 'revisionable_id', 'number'], 'revisions_item_number_unique');
            $table->index(['revisionable_type', 'revisionable_id', 'kind', 'created_at'], 'revisions_item_kind_created_index');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->string('title');
            $table->string('slug', 191);
            $table->string('path', 512);
            $table->string('published_path', 512)->nullable()->unique();
            $table->text('excerpt')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('template', 32)->default('default');
            $table->string('status', 16)->default('draft');
            $table->boolean('has_unpublished_changes')->default(true);
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('first_published_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'slug']);
            $table->index('path');
            $table->index(['status', 'publish_at']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('revisions');
    }
};
