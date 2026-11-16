<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permission-aware import of third-party marketplace listings.
 *
 *  - import_sources: one row per source website, with the permission scope an
 *    admin has actually recorded (listing data, images, seller contacts).
 *    Everything defaults to pending_authorization: nothing is published until
 *    a person records what the permission covers.
 *  - import_runs: one row per dry-run or import, with exact counts.
 *  - source_listings: provenance + normalized snapshot for every candidate the
 *    importer looked at (selected or rejected), unique per source listing ID
 *    and per canonical URL so repeated runs are idempotent.
 *  - source_listing_images: per-image provenance, hash and stored variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('base_url');
            $table->string('status')->default('active'); // active | paused
            $table->string('listing_data_permission')->default('pending_authorization');
            $table->string('image_permission')->default('pending_authorization');
            $table->string('contact_permission')->default('pending_authorization');
            $table->boolean('attribution_required')->default(true);
            $table->text('permission_reference')->nullable();
            $table->date('permission_granted_at')->nullable();
            $table->unsignedInteger('permission_max_listings')->nullable();
            $table->foreignUuid('permission_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('request_delay_ms')->default(2500);
            $table->text('notes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_source_id')->constrained('import_sources')->cascadeOnDelete();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode'); // dry_run | import | recheck
            $table->string('status')->default('queued'); // queued | running | completed | failed | cancelled
            $table->unsignedInteger('target')->default(35);
            $table->unsignedInteger('candidates_discovered')->default(0);
            $table->unsignedInteger('candidates_fetched')->default(0);
            $table->unsignedInteger('eligible')->default(0);
            $table->unsignedInteger('selected')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('images_stored')->default(0);
            $table->unsignedInteger('contacts_pending')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('source_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_source_id')->constrained('import_sources')->cascadeOnDelete();
            $table->foreignUuid('last_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->foreignUuid('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('source_listing_id');
            $table->string('source_url', 500);
            $table->string('canonical_url', 500);
            // candidate | eligible | rejected | selected | imported | published | failed | removed
            $table->string('status')->default('candidate')->index();
            $table->decimal('selection_score', 6, 2)->nullable();
            $table->unsignedTinyInteger('completeness_score')->nullable();
            $table->unsignedTinyInteger('extraction_confidence')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('rejection_reasons')->nullable();
            $table->json('data')->nullable();            // normalized, factual fields
            $table->longText('source_description')->nullable(); // internal only, never public
            $table->string('content_hash', 64)->nullable();
            $table->string('duplicate_fingerprint', 64)->nullable()->index();
            $table->date('source_published_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('source_active')->default(true);
            $table->timestamp('imported_at')->nullable();
            $table->string('image_publication_status')->default('pending_authorization');
            $table->string('contact_publication_status')->default('pending_authorization');
            $table->string('seller_type')->nullable();          // dealer | individual
            $table->string('seller_display_name')->nullable();  // protected
            $table->text('seller_phone')->nullable();           // encrypted; only when explicitly authorized
            $table->string('seller_phone_e164')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['import_source_id', 'source_listing_id']);
            $table->unique('canonical_url');
        });

        Schema::create('source_listing_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_listing_id')->constrained('source_listings')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('source_url', 500);
            $table->string('sha256', 64)->nullable();
            $table->string('original_path')->nullable(); // private disk
            $table->string('webp_path')->nullable();     // private disk
            $table->string('thumb_path')->nullable();    // private disk
            $table->json('public_paths')->nullable();    // set only after image permission is authorized
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->string('status')->default('pending'); // pending | stored | duplicate | failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['source_listing_id', 'position']);
            $table->index('sha256');
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'manage_imports', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('name', 'manage_imports')->where('guard_name', 'web')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['super_admin', 'admin'])->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    public function down(): void
    {
        Schema::dropIfExists('source_listing_images');
        Schema::dropIfExists('source_listings');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('import_sources');
        DB::table('permissions')->where('name', 'manage_imports')->delete();
    }
};
