<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Whether the default page header (title, excerpt) is shown visually. The H1 stays
            // in the page for screen readers when hidden.
            $table->boolean('show_title')->default(true)->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('show_title');
        });
    }
};
