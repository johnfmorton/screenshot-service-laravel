<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('screenshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('api_key_id');
            $table->string('url');
            $table->string('url_hash', 64);
            $table->unsignedInteger('viewport_width');
            $table->unsignedInteger('viewport_height');
            $table->unsignedInteger('max_width')->nullable();
            $table->unsignedInteger('thumbnail_width');
            $table->unsignedInteger('thumbnail_height');
            $table->string('status')->default('pending');
            $table->string('full_image_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('error_message')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('cascade');
            $table->index('url_hash');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
