<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * error_message is returned to API clients and webhooks. Raw exception text
 * used to go there, including Browsershot's full node command line, server
 * paths and stack traces. The raw text now lives here, for admins only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->text('error_detail')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropColumn('error_detail');
        });
    }
};
