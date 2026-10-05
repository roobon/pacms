<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 32);
            $table->unsignedBigInteger('seoable_id');
            $table->string('title')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 512)->unique();
            $table->string('target_path', 1024);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_auto')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_references', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->char('block_uuid', 26)->nullable();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('context', 32);

            $table->index(['target_type', 'target_id']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_references');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('seo_metadata');
    }
};
