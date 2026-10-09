<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — Navigation: menus and their items (DATABASE-ARCHITECTURE.md §9), and a page's
 * own header and footer (the site default, none, or a chosen global block).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('location', 32)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 16);
            $table->string('label', 191)->nullable();
            $table->string('linkable_type', 32)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('icon', 64)->nullable();
            $table->string('css_class', 191)->nullable();
            $table->string('visibility', 16)->default('everyone');
            $table->boolean('is_mega')->default(false);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
            $table->index(['linkable_type', 'linkable_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('header_mode', 8)->default('default')->after('show_title');
            $table->foreignId('header_global_block_id')->nullable()->after('header_mode')->constrained('global_blocks')->nullOnDelete();
            $table->string('footer_mode', 8)->default('default')->after('header_global_block_id');
            $table->foreignId('footer_global_block_id')->nullable()->after('footer_mode')->constrained('global_blocks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('header_global_block_id');
            $table->dropConstrainedForeignId('footer_global_block_id');
            $table->dropColumn(['header_mode', 'footer_mode']);
        });
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
