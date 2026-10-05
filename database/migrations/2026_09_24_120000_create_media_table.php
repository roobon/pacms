<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->string('kind', 16);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->string('credit')->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->json('focal_point')->nullable();
            $table->json('variants')->nullable();
            $table->char('checksum_sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
            $table->fullText(['original_name', 'alt', 'caption']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
