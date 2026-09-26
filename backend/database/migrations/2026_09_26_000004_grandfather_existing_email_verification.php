<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email verification was introduced after launch, and posting listings or
 * sending inquiries now requires it. Accounts that existed before this
 * release never had a way to verify, so treat them as verified rather than
 * locking every existing user out on deploy. New sign-ups verify normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Irreversible by design: we can't tell grandfathered rows apart.
    }
};
