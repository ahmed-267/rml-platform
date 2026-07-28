<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('rejection_reason_code')->nullable()->after('rejection_reason');
            $table->text('rejection_comment')->nullable()->after('rejection_reason_code');
            $table->foreignId('rejected_by_user_id')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('rejection_reason_code')->nullable()->after('approval_status');
            $table->text('rejection_reason')->nullable()->after('rejection_reason_code');
            $table->text('rejection_comment')->nullable()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejection_comment');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejection_reason_code', 'rejection_comment']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'rejection_reason_code',
                'rejection_reason',
                'rejection_comment',
                'rejected_at',
            ]);
        });
    }
};
