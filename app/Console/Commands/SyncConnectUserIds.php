<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncConnectUserIds extends Command
{
    protected $signature = 'tv:sync-connect-user-ids {--dry-run : Report changes without saving them}';

    protected $description = 'Backfill TV Connect user IDs from verified Core user records';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            $this->error('Core API is not configured.');

            return self::FAILURE;
        }

        $client = Http::withToken($token)->acceptJson()->timeout(15);
        $host = trim((string) config('services.core_api.host'));

        if ($host !== '') {
            $client = $client->withHeaders(['Host' => $host]);
        }

        $updated = 0;
        $skipped = 0;

        User::query()
            ->whereNotNull('network_user_id')
            ->whereNull('connect_user_id')
            ->orderBy('id')
            ->each(function (User $user) use ($baseUrl, $client, &$updated, &$skipped): void {
                $coreUserId = (int) $user->network_user_id;

                try {
                    $response = $client->get("{$baseUrl}/api/users", [
                        'search' => (string) $coreUserId,
                        'limit' => 25,
                    ]);
                } catch (\Throwable $exception) {
                    $this->warn("Skipped TV user {$user->id}: Core request failed.");
                    $skipped++;

                    return;
                }

                $records = $response->json('data');
                $coreUser = is_array($records)
                    ? collect($records)->first(fn ($record) => is_array($record) && (int) ($record['network_id'] ?? 0) === $coreUserId)
                    : null;
                $returnedCoreId = (int) (is_array($coreUser) ? ($coreUser['connect_user_id'] ?? $coreUser['id'] ?? 0) : 0);

                if (! $response->successful() || ! is_array($coreUser) || $returnedCoreId < 1) {
                    $this->warn("Skipped TV user {$user->id}: Core identity could not be verified.");
                    $skipped++;

                    return;
                }

                $connectUserId = $returnedCoreId;
                $conflict = User::withTrashed()
                    ->where('connect_user_id', $connectUserId)
                    ->whereKeyNot($user->id)
                    ->exists();

                if ($connectUserId < 1 || $conflict) {
                    $this->warn("Skipped TV user {$user->id}: Connect ID is unavailable or already linked.");
                    $skipped++;

                    return;
                }

                if (! $this->option('dry-run')) {
                    $user->forceFill(['connect_user_id' => $connectUserId])->save();
                }

                $this->line("TV user {$user->id}: {$coreUserId} -> Connect {$connectUserId}");
                $updated++;
            });

        $this->info("{$updated} user(s) " . ($this->option('dry-run') ? 'would be' : '') . " linked; {$skipped} skipped.");

        return self::SUCCESS;
    }
}
