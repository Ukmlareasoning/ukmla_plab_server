<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'admin_module_access')) {
                $table->json('admin_module_access')->nullable()->after('user_status');
            }
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN user_status ENUM('normal','admin','sub-admin') DEFAULT 'normal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('user_status', 'sub-admin')->update(['user_status' => 'normal']);
        DB::statement("ALTER TABLE users MODIFY COLUMN user_status ENUM('normal','admin') DEFAULT 'normal'");

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'admin_module_access')) {
                $table->dropColumn('admin_module_access');
            }
        });
    }
};

