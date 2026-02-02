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
        Schema::table('blacklisted_tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('jti');
            $table->unique('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blacklisted_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
