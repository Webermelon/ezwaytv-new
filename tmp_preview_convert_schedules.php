<?php
// Preview conversion of live_tv_channel_schedules from Asia/Dhaka -> UTC
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$rows = DB::table('live_tv_channel_schedules')->whereNull('deleted_at')->orderBy('id')->get();
if(count($rows) === 0){ echo "NO_ROWS\n"; exit; }

echo "Previewing conversion of " . count($rows) . " schedules (Asia/Dhaka -> UTC)\n";
$sample = 0;
foreach($rows as $r){
    $oldStart = $r->start_at;
    $oldEnd = $r->end_at;
    // try to get channel timezone, fall back to app timezone
    $channelTz = DB::table('live_tv_channel')->where('id', $r->live_tv_channel_id)->value('timezone');
    $useTz = $channelTz ?: config('app.timezone', 'UTC');
    $newStart = $oldStart ? Carbon::parse($oldStart, $useTz)->setTimezone('UTC')->toDateTimeString() : null;
    $newEnd = $oldEnd ? Carbon::parse($oldEnd, $useTz)->setTimezone('UTC')->toDateTimeString() : null;
    if($oldStart !== $newStart || $oldEnd !== $newEnd){
        echo "ID={$r->id} CH={$r->live_tv_channel_id}\n";
        echo "  start:  {$oldStart}  ->  {$newStart}\n";
        echo "  end:    {$oldEnd}  ->  {$newEnd}\n";
        $sample++;
        if($sample >= 50){ echo "-- showing first 50 changes --\n"; break; }
    }
}
if($sample === 0) echo "No changes detected (rows already in UTC or identical when converted).\n";

echo "\nTo apply changes, run: php tmp_apply_convert_schedules.php\n";
