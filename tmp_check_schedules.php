<?php
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\LiveTV\Models\LiveTvChannel;

$channel = LiveTvChannel::where('slug','ezway-tv')->first();
if(!$channel){ echo "NO_CHANNEL\n"; exit; }
echo "CHANNEL_ID=" . $channel->id . "\n";
$schedules = DB::table('live_tv_channel_schedules')->where('live_tv_channel_id', $channel->id)->whereNull('deleted_at')->orderBy('start_at')->get();
echo "SCHEDULE_COUNT=" . count($schedules) . "\n";
foreach($schedules as $s){ echo $s->id . '|' . ($s->title ?? '-') . '|' . ($s->start_at ?? '-') . '|' . ($s->end_at ?? '-') . "\n"; }
