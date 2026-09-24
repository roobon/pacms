<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 64);
            $table->foreignId('parent_id')->nullable()->constrained('terms')->restrictOnDelete();
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['taxonomy', 'slug']);
            $table->index(['taxonomy', 'parent_id', 'position']);
        });

        Schema::create('termables', function (Blueprint $table) {
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('termable_type', 32);
            $table->unsignedBigInteger('termable_id');
            $table->unsignedInteger('position')->default(0);

            $table->primary(['term_id', 'termable_type', 'termable_id']);
            $table->index(['termable_type', 'termable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termables');
        Schema::dropIfExists('terms');
    }
};
