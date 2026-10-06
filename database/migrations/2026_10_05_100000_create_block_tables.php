<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->string('icon', 64);
            $table->boolean('is_core')->default(true);
            $table->string('status', 16)->default('published');
            $table->json('fields')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('defaults')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('parent_id')->nullable()->constrained('blocks')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('block_type_id')->constrained('block_types')->restrictOnDelete();
            $table->string('name', 120)->nullable();
            $table->json('content')->nullable();
            $table->json('source')->nullable();
            $table->json('display')->nullable();
            $table->json('layout')->nullable();
            $table->json('style')->nullable();
            $table->json('responsive')->nullable();
            $table->json('advanced')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'parent_id', 'position'], 'blocks_owner_tree_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('block_types');
    }
};
