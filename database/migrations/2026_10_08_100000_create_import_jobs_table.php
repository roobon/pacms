<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JSON imports (CMS-BLOCK-SCHEMA.md §17): one row per analysed document, kept with its
 * report so editors (and auditors) can see what was imported and what was changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);                 // block | section | template | page
            $table->string('status', 24);               // awaiting_confirmation | importing | completed | failed
            $table->string('title')->nullable();
            $table->string('schema_version', 8);
            $table->longText('source')->nullable();     // the submitted JSON, so it can be corrected and checked again
            $table->longText('document');               // translated document (internal format, assets pending)
            $table->json('assets')->nullable();         // asset plan
            $table->json('report');
            $table->json('options')->nullable();        // confirmed choices (target, asset strategies)
            $table->string('result_type', 32)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
