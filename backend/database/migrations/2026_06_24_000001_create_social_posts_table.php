<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('listing_id')->nullable();
            $table->string('platform')->default('instagram');
            $table->string('status')->default('draft'); // draft | approved | publishing | published | failed
            $table->string('source_image')->nullable();   // chosen listing photo
            $table->text('image_url')->nullable();         // rendered Cloudinary post image
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('ig_media_id')->nullable();
            $table->text('ig_permalink')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'platform']);
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
