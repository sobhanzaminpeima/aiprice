<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct buyer → seller contact:
 *  - inquiries.share_contact: the buyer agreed to share their name, email and
 *    phone with the seller (otherwise only the message is shown).
 *  - listing_contact_reveals: who revealed a seller's full phone number. The
 *    full number is only ever returned to signed-in users, and each reveal is
 *    recorded so sellers and staff can see real leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->boolean('share_contact')->default(false)->after('message');
        });

        Schema::create('listing_contact_reveals', function (Blueprint $table) {
            $table->id();
            $table->uuid('listing_id')->index();
            $table->uuid('user_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_contact_reveals');
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('share_contact');
        });
    }
};
