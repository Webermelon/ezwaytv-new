<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_channel_entitlements', function (Blueprint $t): void {
            $t->uuid('assignment_id')->primary();
            $t->unsignedBigInteger('subscription_id')->unique();
            $t->unsignedBigInteger('member_id')->index();
            $t->unsignedBigInteger('channel_id')->unique();
            $t->string('status', 24);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('paid_until')->nullable()->index();
            $t->unsignedBigInteger('version');
            $t->char('payload_hash', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    { /* Retain managed-channel markers to prevent bypass after rollback. */
    }
};
