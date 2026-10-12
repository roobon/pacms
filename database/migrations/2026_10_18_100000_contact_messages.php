<?php

use App\Auth\RolePermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11A: messages sent with Contact form blocks (P-1: e-mailed and kept 90 days). No IP
 * address is stored; rate limiting only uses it in the cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->string('recipient', 191);
            $table->string('page_url', 2048)->nullable();
            $table->char('block_uuid', 26)->nullable();
            $table->boolean('consent')->default(false);
            $table->boolean('mailed')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'created_at']);
        });

        // The new "Manage messages" permission for existing installations.
        app(RolePermissionSynchronizer::class)->sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
