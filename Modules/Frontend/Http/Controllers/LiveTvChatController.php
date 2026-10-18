<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Models\LiveTvChatMessage;

class LiveTvChatController extends Controller
{
    private const SESSION_KEY = 'livetv_chat_guest_name';
    private const COOKIE_KEY = 'livetv_chat_identity';
    private const COOKIE_MINUTES = 216000;
    private const NAME_PREFIX = 'Anonymous';

    public function index(Request $request, int $channelId)
    {
        $channel = $this->resolveChannel($channelId);

        if (! $this->isChatEnabled($channel)) {
            return response()->json([
                'enabled' => false,
                'guest_name' => session(self::SESSION_KEY),
                'identity_type' => 'guest',
                'can_change_name' => true,
                'can_post_links' => false,
                'can_send_promotions' => false,
                'promotion_packages' => [],
                'title' => $channel->chat_title ?: 'Live Chat',
                'ads_enabled' => false,
                'ad_rotation_seconds' => 3,
                'ad_banners' => [],
                'messages' => [],
            ]);
        }

        [$identity, $cookieValue] = $this->resolveIdentity($request, false);

        $canSendPromotions = $this->canPostLinks();
        $response = response()->json([
            'enabled' => true,
            'guest_name' => $identity['display_name'],
            'identity_type' => $identity['type'],
            'can_change_name' => $identity['type'] === 'guest',
            'can_post_links' => $canSendPromotions,
            'can_send_promotions' => $canSendPromotions,
            'promotion_packages' => $canSendPromotions ? $this->promotionPackages(false) : [],
            'title' => $channel->chat_title ?: 'Live Chat',
            'ads_enabled' => (bool) $channel->chat_ads_enabled,
            'ad_rotation_seconds' => 3,
            'ad_banners' => $channel->chat_ads_enabled ? ($channel->chat_ad_banners ?? []) : [],
            'messages' => $this->formatMessages($channel),
        ]);

        return $cookieValue ? $response->cookie($this->makeIdentityCookie($cookieValue)) : $response;
    }

    public function storeGuest(Request $request, int $channelId)
    {
        $channel = $this->resolveChannel($channelId);

        if (! $this->isChatEnabled($channel)) {
            return response()->json([
                'message' => 'Live chat is disabled for this channel.',
            ], 403);
        }

        if ($request->boolean('logout')) {
            $request->session()->forget(self::SESSION_KEY);

            return response()->json([
                'guest_name' => null,
                'identity_type' => 'guest',
                'can_change_name' => true,
            ])->withCookie(Cookie::forget(self::COOKIE_KEY, '/'));
        }

        $guestName = $request->filled('guest_name')
            ? $this->normalizeGuestName((string) $request->input('guest_name'))
            : $this->generateAnonymousName();

        if ($guestName === '') {
            return response()->json([
                'message' => 'Enter a valid display name.',
            ], 422);
        }

        $identity = $this->buildGuestIdentity($guestName);
        $cookieValue = $this->encodeIdentityCookie($identity);
        session([self::SESSION_KEY => $guestName]);

        return response()->json([
            'guest_name' => $identity['display_name'],
            'identity_type' => 'guest',
            'can_change_name' => true,
        ])->cookie($this->makeIdentityCookie($cookieValue));
    }

    public function storeMessage(Request $request, int $channelId)
    {
        $channel = $this->resolveChannel($channelId);

        if (! $this->isChatEnabled($channel)) {
            return response()->json([
                'message' => 'Live chat is disabled for this channel.',
            ], 403);
        }

        [$identity] = $this->resolveIdentity($request, false);
        $displayName = $identity['display_name'] ?? null;

        if (! $displayName) {
            return response()->json([
                'message' => 'Unable to initialize chat identity.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'promotion_package_ids' => ['nullable', 'array', 'max:10'],
            'promotion_package_ids.*' => ['integer', 'distinct'],
        ]);

        $message = $this->normalizeMessage((string) ($validated['message'] ?? ''));
        $packageIds = collect($validated['promotion_package_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($message === '' && $packageIds->isEmpty()) {
            return response()->json([
                'message' => 'Add a message or select at least one promotional package.',
            ], 422);
        }

        if ($packageIds->isNotEmpty() && ! $this->canPostLinks()) {
            return response()->json([
                'message' => 'Your account is not allowed to send promotional packages in live chat.',
            ], 403);
        }

        $promotionPackages = [];
        if ($packageIds->isNotEmpty()) {
            try {
                $availablePackages = collect($this->promotionPackages(true))->keyBy(fn (array $package) => (int) $package['id']);
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'Promotional packages are temporarily unavailable. Please try again.',
                ], 502);
            }

            $promotionPackages = $packageIds
                ->map(fn (int $id) => $availablePackages->get($id))
                ->filter()
                ->values()
                ->all();

            if (count($promotionPackages) !== $packageIds->count()) {
                return response()->json([
                    'message' => 'One or more selected promotional packages are no longer available.',
                ], 422);
            }
        }

        if ($this->containsLink($message) && ! $this->canPostLinks()) {
            return response()->json([
                'message' => 'Your account is not allowed to post links in live chat.',
            ], 422);
        }

        if ($this->wordCount($message) > 120) {
            return response()->json([
                'message' => 'Messages must be 120 words or fewer.',
            ], 422);
        }

        $throttleKey = 'livetv-chat:' . $channel->id . ':' . $request->session()->getId();
        if (Cache::has($throttleKey)) {
            return response()->json([
                'message' => 'Please wait a moment before sending another message.',
            ], 429);
        }

        Cache::put($throttleKey, true, now()->addSeconds(3));

        $chatMessage = LiveTvChatMessage::create([
            'live_tv_channel_id' => $channel->id,
            'user_id' => $identity['type'] === 'user' ? auth()->id() : null,
            'guest_name' => $displayName,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'message' => $message,
            'promotion_packages' => $promotionPackages ?: null,
            'ip_address' => $request->ip(),
        ])->load('user:id,file_url');

        return response()->json([
            'message' => $this->transformMessage($chatMessage),
        ], 201);
    }

    private function resolveChannel(int $channelId): LiveTvChannel
    {
        return LiveTvChannel::query()
            ->whereKey($channelId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function isChatEnabled(LiveTvChannel $channel): bool
    {
        $globalEnabled = (int) (GetSettingValue('live_tv_chat_enabled') ?? 1) === 1;

        return $globalEnabled && (bool) $channel->enable_live_chat;
    }

    private function formatMessages(LiveTvChannel $channel): array
    {
        return LiveTvChatMessage::query()
            ->where('live_tv_channel_id', $channel->id)
            ->with('user:id,file_url')
            ->latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (LiveTvChatMessage $message) => $this->transformMessage($message))
            ->all();
    }

    private function transformMessage(LiveTvChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'guest_name' => $message->guest_name,
            'avatar_url' => $message->avatar_url ?: $this->accountAvatarUrl($message->user),
            'message' => $message->message,
            'promotion_packages' => $message->promotion_packages ?? [],
            'time' => optional($message->created_at)->diffForHumans(),
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }

    private function normalizeGuestName(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return mb_substr($value, 0, 40);
    }

    private function resolveIdentity(Request $request, bool $createGuestIfMissing = false): array
    {
        if (auth()->check()) {
            $user = auth()->user();
            $identity = [
                'type' => 'user',
                'display_name' => trim((string) $user->full_name) ?: ('User ' . auth()->id()),
                'email' => $user->email,
                'user_id' => auth()->id(),
                'avatar_url' => $this->activeProfileAvatarUrl($user),
            ];

            session([self::SESSION_KEY => $identity['display_name']]);

            return [$identity, null];
        }

        $identity = $this->decodeIdentityCookie((string) $request->cookie(self::COOKIE_KEY, ''));

        if ($identity) {
            session([self::SESSION_KEY => $identity['display_name']]);

            return [$identity, null];
        }

        if (! $createGuestIfMissing) {
            return [[
                'type' => 'guest',
                'display_name' => null,
                'email' => null,
                'user_id' => null,
                'avatar_url' => null,
            ], null];
        }

        $identity = $this->buildGuestIdentity();
        $cookieValue = $this->encodeIdentityCookie($identity);
        session([self::SESSION_KEY => $identity['display_name']]);

        return [$identity, $cookieValue];
    }

    private function buildGuestIdentity(?string $guestName = null): array
    {
        $name = $guestName ?: $this->generateAnonymousName();
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '.', trim($name)));

        return [
            'type' => 'guest',
            'display_name' => $name,
            'email' => ($slug ?: 'anonymous') . '@guest.ezway.tv',
            'user_id' => null,
            'avatar_url' => null,
        ];
    }

    private function decodeIdentityCookie(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        $displayName = $this->normalizeGuestName((string) ($decoded['display_name'] ?? ''));
        if ($displayName === '') {
            return null;
        }

        return [
            'type' => 'guest',
            'display_name' => $displayName,
            'email' => $decoded['email'] ?? null,
            'user_id' => null,
            'avatar_url' => null,
        ];
    }

    private function activeProfileAvatarUrl($user): ?string
    {
        $profile = getCurrentProfileSession();
        if ($profile && (int) ($profile->user_id ?? 0) === (int) $user->id) {
            $avatar = trim((string) ($profile->avatar ?? ''));
            if ($avatar !== '') {
                return $this->resolveAvatarUrl($avatar);
            }
        }

        return $this->accountAvatarUrl($user);
    }

    private function accountAvatarUrl($user): ?string
    {
        if (! $user) {
            return null;
        }

        $avatar = trim((string) ($user->file_url ?? ''));
        if ($avatar === '') {
            $avatar = trim((string) $user->getFirstMediaUrl('profile_image'));
        }

        return $avatar !== '' ? $this->resolveAvatarUrl($avatar) : null;
    }

    private function resolveAvatarUrl(string $avatar): string
    {
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        return setBaseUrlWithFileName($avatar, 'image', 'users');
    }

    private function encodeIdentityCookie(array $identity): string
    {
        return json_encode([
            'type' => $identity['type'],
            'display_name' => $identity['display_name'],
            'email' => $identity['email'],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function makeIdentityCookie(string $payload)
    {
        return Cookie::make(self::COOKIE_KEY, $payload, self::COOKIE_MINUTES, '/');
    }

    private function generateAnonymousName(): string
    {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return self::NAME_PREFIX . ' ' . $suffix;
    }

    private function normalizeMessage(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }

    private function containsLink(string $value): bool
    {
        return (bool) preg_match('/((https?:\/\/|www\.)\S+)|(\b[a-z0-9][a-z0-9\-]*\.(com|net|org|tv|io|co|me|ly|us|uk|info|biz|app|dev|gg|live|fm|ai|edu|gov)(\/\S*)?\b)/iu', $value);
    }

    private function canPostLinks(): bool
    {
        $email = strtolower(trim((string) auth()->user()?->email));
        if ($email === '') {
            return false;
        }

        $allowedEmails = preg_split(
            '/[\s,;]+/',
            strtolower((string) GetSettingValue('live_tv_chat_link_allowed_emails')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return in_array($email, $allowedEmails ?: [], true);
    }

    private function promotionPackages(bool $fresh): array
    {
        $loader = function (): array {
            $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
            $token = trim((string) config('services.core_api.token'));
            if ($baseUrl === '' || $token === '') {
                return [];
            }

            $client = Http::withToken($token)->acceptJson()->timeout(12);
            $host = trim((string) config('services.core_api.host'));
            if ($host !== '') {
                $client = $client->withHeaders(['Host' => $host]);
            }

            $response = $client->get($baseUrl.'/api/tv-channel-chat/packages');
            $response->throw();

            return collect($response->json('data', []))
                ->filter(fn ($package) => is_array($package) && isset($package['id'], $package['name']))
                ->map(fn (array $package) => $this->promotionPackageSnapshot($package))
                ->values()
                ->all();
        };

        if ($fresh) {
            return $loader();
        }

        try {
            return Cache::remember('livetv-chat:promotion-packages', now()->addMinute(), $loader);
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function promotionPackageSnapshot(array $package): array
    {
        $billing = is_array($package['billing'] ?? null) ? $package['billing'] : [];
        $categories = is_array($package['categories'] ?? null) ? $package['categories'] : [];
        $firstCategory = is_array($categories[0] ?? null) ? $categories[0] : [];
        $packageSlug = mb_substr(trim((string) ($package['slug'] ?? '')), 0, 255);
        $categorySlug = mb_substr(trim((string) ($firstCategory['slug'] ?? '')), 0, 255);

        return [
            'id' => (int) $package['id'],
            'slug' => $packageSlug,
            'name' => mb_substr(trim((string) $package['name']), 0, 160),
            'description' => mb_substr(trim(strip_tags((string) ($package['description'] ?? ''))), 0, 500),
            'image_url' => filter_var($package['image_url'] ?? null, FILTER_VALIDATE_URL) ? $package['image_url'] : null,
            'category_slug' => $categorySlug ?: null,
            'checkout_url' => $this->promotionCheckoutUrl($packageSlug, $categorySlug),
            'billing' => [
                'price' => isset($billing['price']) && is_numeric($billing['price']) ? (float) $billing['price'] : null,
                'currency' => mb_substr(trim((string) ($billing['currency'] ?? 'USD')), 0, 8),
                'period' => mb_substr(trim((string) ($billing['period'] ?? '')), 0, 30) ?: null,
            ],
        ];
    }

    private function promotionCheckoutUrl(string $packageSlug, string $categorySlug): ?string
    {
        $baseUrl = rtrim(trim((string) config('services.ezway_promotion.url')), '/');
        if ($packageSlug === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $query = [];
        if ($categorySlug !== '') {
            $query['category'] = $categorySlug;
        }
        $query['package'] = $packageSlug;

        return $baseUrl.'/core-checkout/?'.http_build_query($query);
    }

    private function wordCount(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY));
    }
}
