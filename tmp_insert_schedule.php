<?php
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$channel = DB::table('live_tv_channel')->where('slug','ezway-tv')->first();
if(!$channel){ echo "NO_CHANNEL\n"; exit; }
$now = (new DateTime())->format('Y-m-d H:i:s');
$later = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
$id = DB::table('live_tv_channel_schedules')->insertGetId([
    'live_tv_channel_id' => $channel->id,
    'title' => 'Test Program',
    'start_at' => $now,
    'end_at' => $later,
    'meta' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);

echo "INSERTED_ID=" . $id . "\n";
