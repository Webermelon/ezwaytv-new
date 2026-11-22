<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vod_channel_entitlement_history', function (Blueprint $t) {
            $t->uuid('assignment_id')->primary(); $t->uuid('replaced_by')->index();
            $t->unsignedBigInteger('channel_id')->index(); $t->unsignedBigInteger('subscription_id')->index();
            $t->unsignedBigInteger('member_id')->index(); $t->longText('snapshot'); $t->dateTime('replaced_at');
        });
    }
    public function down(): void { /* Preserve billing provenance and stale-message protection. */ }
};
