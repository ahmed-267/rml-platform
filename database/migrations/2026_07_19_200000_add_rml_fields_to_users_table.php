<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('email');
            $table->string('locale', 5)->default('en')->after('approval_status');
            $table->string('phone')->nullable()->after('locale');
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'approval_status',
                'locale',
                'phone',
                'approved_at',
            ]);
        });
    }
};
