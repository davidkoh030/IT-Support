<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when the user is a named contact of an external vendor
            // (role: vendor). Such users may only see tickets assigned to
            // this vendor or explicitly shared with them.
            $table->foreignId('vendor_id')->nullable()->after('preferred_contact')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
