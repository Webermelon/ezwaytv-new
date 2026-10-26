<?php

namespace Modules\Statistics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\Statistics\Models\ContentBoost;
use Modules\Statistics\Models\StatSetting;

class BoostEzwayMoviesViewsCommand extends Command
{
    protected $signature = 'statistics:boost-ezway-movies-views
                            {--force : Run immediately even when this half-hour window was already processed}
                            {--channel= : Run only one managed Live TV channel ID}';

    protected $description = 'Apply configured half-hour display-view increments to managed Live TV channels.';

    private const CHANNEL_IDS = [37, 69, 93];
    private const CONFIG_KEY = 'livetv_view_automation_channels';

    public function handle(): int
    {
        $now = now('UTC');
        $window = $now->format('Y-m-d H:') . ($now->minute < 30 ? '00' : '30');
        $channelIds = $this->option('channel') === null
            ? self::CHANNEL_IDS
            : [(int) $this->option('channel')];
        $channelIds = array_values(array_intersect(self::CHANNEL_IDS, $channelIds));
        if ($channelIds === []) {
            $this->error('The requested channel is not managed by this automation.');
            return self::FAILURE;
        }

        $applied = DB::transaction(function () use ($window, $channelIds) {
            $config = $this->channelConfig();
            $channels = LiveTvChannel::whereIn('id', $channelIds)->where('status', 1)->get()->keyBy('id');
            $count = 0;

            foreach ($channelIds as $channelId) {
                $channelConfig = $config[$channelId];
                if (! $channelConfig['enabled'] || ! isset($channels[$channelId])) {
                    continue;
                }

                $lastWindowKey = "livetv_view_automation_last_window:{$channelId}";
                $lastRun = StatSetting::where('key', $lastWindowKey)->lockForUpdate()->first();
                if (! $this->option('force') && $lastRun?->value === $window) {
                    continue;
                }

                $channel = $channels[$channelId];
                $increment = $channelConfig['increment'];
                ContentBoost::create([
                    'content_type' => 'livetv',
                    'content_id' => $channelId,
                    'content_name' => $channel->name,
                    'boost_views' => $increment,
                    'boost_plays' => 0,
                    'boost_watch_seconds' => 0,
                    'boost_unique_visitors' => 0,
                    'note' => ($this->option('force') ? 'Manual' : 'Scheduled') . " {$channel->name} display increment (+{$increment}) for {$window} UTC",
                ]);

                StatSetting::updateOrCreate(['key' => $lastWindowKey], ['value' => $window]);
                $count++;
            }

            return $count;
        });

        if ($applied === 0) {
            $this->line('No Live TV view increment was applied.');
            return self::SUCCESS;
        }

        clearLiveTvDashboardCache();
        cache()->forget('stat_settings_all');
        $this->info("Applied scheduled display-view increments to {$applied} Live TV channel(s).");

        return self::SUCCESS;
    }

    private function channelConfig(): array
    {
        $stored = json_decode((string) StatSetting::get(self::CONFIG_KEY, '{}'), true);
        $stored = is_array($stored) ? $stored : [];
        $defaults = [37 => false, 69 => true, 93 => false];

        foreach ($defaults as $channelId => $enabled) {
            $stored[$channelId] = [
                'enabled' => (bool) ($stored[$channelId]['enabled'] ?? $enabled),
                'increment' => max(1, min(100000, (int) ($stored[$channelId]['increment'] ?? 500))),
            ];
        }

        return $stored;
    }
}
