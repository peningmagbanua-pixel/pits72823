<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sessions table was created with a bigint user_id (Laravel's
     * default), but this application uses UUID string primary keys.
     * An authenticated session could therefore never be written:
     * MySQL rejected the UUID in strict mode, so every login bounced
     * straight back to the login page. Widen the column to fit UUIDs.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
