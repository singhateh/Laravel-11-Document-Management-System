<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Explicit short name — auto-generated name exceeds MySQL's 64-char limit.
    private const IDX = 'notif_notifiable_status_idx';

    /**
     * Rename user_id/user_type → notifiable_id/notifiable_type (Laravel morphTo convention).
     * Replace polymorphic created_by pair with a typed FK created_by_user_id → users.id.
     *
     * IDEMPOTENT: each step is guarded so re-running after a partial failure is safe.
     * (MySQL DDL is auto-committed per statement; a partial run can leave the schema
     * in an intermediate state if a later statement fails.)
     */
    public function up(): void
    {
        // Step 1 — drop old index (only if user_id still has it)
        if (Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'status', 'dismiss_status']);
            });
        }

        // Step 2 — rename notifiable pair (only if the old names still exist)
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'user_id')) {
                $table->renameColumn('user_id', 'notifiable_id');
            }
            if (Schema::hasColumn('notifications', 'user_type')) {
                $table->renameColumn('user_type', 'notifiable_type');
            }
        });

        // Step 3 — drop old polymorphic created_by pair
        $dropCols = array_filter(
            ['created_by_id', 'created_by_type'],
            fn ($col) => Schema::hasColumn('notifications', $col)
        );
        if ($dropCols) {
            Schema::table('notifications', function (Blueprint $table) use ($dropCols) {
                $table->dropColumn(array_values($dropCols));
            });
        }

        // Step 4 — add typed FK (creator is always a User)
        if (!Schema::hasColumn('notifications', 'created_by_user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->foreignId('created_by_user_id')
                      ->nullable(false)
                      ->constrained('users')
                      ->after('dismiss_status');
            });
        }

        // Step 5 — re-create composite index with an explicit short name
        $existing = DB::select("SHOW INDEX FROM notifications WHERE Key_name = ?", [self::IDX]);
        if (empty($existing)) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(
                    ['notifiable_id', 'notifiable_type', 'status', 'dismiss_status'],
                    self::IDX
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(self::IDX);
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');

            $table->string('created_by_id')->after('dismiss_status');
            $table->string('created_by_type')->after('created_by_id');

            $table->renameColumn('notifiable_id', 'user_id');
            $table->renameColumn('notifiable_type', 'user_type');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'dismiss_status']);
        });
    }
};
