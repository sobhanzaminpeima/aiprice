<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public contact form creates support tickets for people without an
 * account, so a ticket may belong to a guest identified only by name + email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('source', 20)->default('dashboard')->after('guest_email'); // dashboard, contact_form
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'source']);
        });
    }
};
