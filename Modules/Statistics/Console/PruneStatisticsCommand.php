<?php

namespace Modules\Statistics\Console;

use Illuminate\Console\Command;
use Modules\Statistics\Models\PageView;
use Modules\Statistics\Models\PlayEvent;
use Modules\Statistics\Models\StatSetting;

class PruneStatisticsCommand extends Command
{
    protected $name = 'statistics:prune';
    protected $description = 'Delete statistics records older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) StatSetting::get('retention_days', 365);

        if ($days <= 0) {
            $this->info('Retention is set to keep forever (0). Nothing pruned.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->toDateString();

        $views = PageView::where('view_date', '<', $cutoff)->delete();
        $plays = PlayEvent::where('play_date', '<', $cutoff)->delete();

        $this->info("Pruned {$views} page views and {$plays} play events older than {$days} days.");

        return self::SUCCESS;
    }
}
