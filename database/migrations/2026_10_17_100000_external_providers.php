<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — External providers (CMS-ARCHITECTURE.md §15): one framework for feeds and
 * social sources. Items are synchronised into local storage; visitors never trigger a
 * request to another site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('name', 191);
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('external_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('provider', 32);
            $table->foreignId('account_id')->nullable()->constrained('external_provider_accounts')->restrictOnDelete();
            $table->json('config')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('source_website', 2048)->nullable();
            $table->string('format', 16)->nullable(); // feeds: json, rss, atom (detected)
            $table->string('status', 16)->default('enabled');
            $table->unsignedSmallInteger('sync_interval_minutes')->default(60);
            $table->unsignedSmallInteger('max_items')->default(50);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->string('last_status', 16)->default('never');
            $table->string('last_error', 1024)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_sync_at']);
        });

        Schema::create('external_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_source_id')->constrained('external_sources')->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('item_type', 16)->default('article');
            $table->string('title', 512)->nullable();
            $table->string('link', 2048)->nullable();
            $table->text('excerpt')->nullable();
            $table->mediumText('description')->nullable();
            $table->string('author', 255)->nullable();
            $table->string('category', 255)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['external_source_id', 'external_id']);
            $table->index(['external_source_id', 'published_at']);
        });

        Schema::create('external_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_source_id')->constrained('external_sources')->cascadeOnDelete();
            $table->string('trigger', 16);
            $table->string('status', 16);
            $table->unsignedInteger('items_fetched')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['external_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_sync_logs');
        Schema::dropIfExists('external_items');
        Schema::dropIfExists('external_sources');
        Schema::dropIfExists('external_provider_accounts');
    }
};
