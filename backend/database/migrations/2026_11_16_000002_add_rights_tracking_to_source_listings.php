<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record source check time and rights state for imported listings.
 * rights_status: clearance_required | cleared | denied. Anything other than
 * "cleared" blocks publication (see PublicationPolicy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_listings', function (Blueprint $table) {
            $table->timestamp('source_checked_at')->nullable()->after('last_seen_at');
            $table->string('rights_status')->default('clearance_required')->after('source_checked_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('source_listings', function (Blueprint $table) {
            $table->dropIndex(['rights_status']);
            $table->dropColumn(['source_checked_at', 'rights_status']);
        });
    }
};
