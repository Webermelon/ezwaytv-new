<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
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
                'messages' => [],
            ]);
        }

        [$identity, $cookieValue] = $this->resolveIdentity($request, true);

        $response = response()->json([
            'enabled' => true,
            'guest_name' => $identity['display_name'],
            'identity_type' => $identity['type'],
            'can_change_name' => $identity['type'] === 'guest',
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

        [$identity] = $this->resolveIdentity($request, true);
        $displayName = $identity['display_name'] ?? null;

        if (! $displayName) {
            return response()->json([
                'message' => 'Unable to initialize chat identity.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $this->normalizeMessage($validated['message']);

        if ($message === '') {
            return response()->json([
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        if ($this->containsLink($message)) {
            return response()->json([
                'message' => 'Links are not allowed in live chat.',
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
            'message' => $message,
            'ip_address' => $request->ip(),
        ]);

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
            'guest_name' => $message->guest_name,
            'message' => $message->message,
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
            $identity = [
                'type' => 'user',
                'display_name' => trim((string) auth()->user()->full_name) ?: ('User ' . auth()->id()),
                'email' => auth()->user()->email,
                'user_id' => auth()->id(),
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
        ];
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

    private function wordCount(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY));
    }
}