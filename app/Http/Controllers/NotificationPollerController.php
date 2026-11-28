<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\NotificationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Serves the newest unread notification and its pending list to the poller.
 */
class NotificationPollerController extends Controller
{
    /**
     * How many unseen notifications a single poll may return.
     */
    private const WINDOW = 20;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $seenAtKey = "notifications.seen_at.{$user->id}";
        $seenIdsKey = "notifications.seen_ids.{$user->id}";

        /** @var string|null $seenAt */
        $seenAt = $request->session()->get($seenAtKey);

        if ($seenAt === null) {
            $latest = $user->unreadNotifications()->latest()->first(['id', 'created_at']);
            $watermark = $latest === null ? now() : CarbonImmutable::parse($latest->created_at);

            $seenIds = $latest === null ? [] : $user->unreadNotifications()
                ->where('created_at', $watermark->toDateTimeString())
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->values()
                ->all();

            $request->session()->put($seenAtKey, $watermark->toDateTimeString());
            $request->session()->put($seenIdsKey, $seenIds);

            return $this->respond($user, null, [], 0);
        }

        /** @var array<int, string> $seenIds */
        $seenIds = $request->session()->get($seenIdsKey, []);

        $window = $this->unseen($user, $seenAt, $seenIds)
            ->latest()
            ->limit(self::WINDOW)
            ->get();

        $pending = $window
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return $this->respond(
            $user,
            $window->first(),
            $pending,
            $pending === [] ? 0 : $this->unseen($user, $seenAt, $seenIds)->count(),
        );
    }

    /**
     * Mark the given notifications as seen and drop them from the pending list.
     */
    public function ack(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<int, string> $ids */
        $ids = $request->validate([
            'ids' => ['array'],
            'ids.*' => ['string'],
        ])['ids'] ?? [];

        if ($ids === []) {
            return response()->json(['ok' => true]);
        }

        $acked = $user->unreadNotifications()->whereIn('id', $ids)->get(['id', 'created_at']);

        if ($acked->isEmpty()) {
            return response()->json(['ok' => true]);
        }

        $seenAt = CarbonImmutable::parse($acked->max('created_at'));

        $seenAtKey = "notifications.seen_at.{$user->id}";
        $seenIdsKey = "notifications.seen_ids.{$user->id}";

        /** @var string|null $stored */
        $stored = $request->session()->get($seenAtKey);
        $storedAt = \is_string($stored) ? CarbonImmutable::parse($stored) : null;

        if ($storedAt !== null && $seenAt->lessThan($storedAt)) {
            return response()->json(['ok' => true]);
        }

        $seenIds = $acked
            ->filter(fn (DatabaseNotification $notification): bool => CarbonImmutable::parse($notification->created_at)->equalTo($seenAt))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        if ($storedAt !== null && $seenAt->equalTo($storedAt)) {
            /** @var array<int, string> $previous */
            $previous = $request->session()->get($seenIdsKey, []);
            $seenIds = array_values(array_unique([...$previous, ...$seenIds]));
        }

        $request->session()->put($seenAtKey, $seenAt->toDateTimeString());
        $request->session()->put($seenIdsKey, $seenIds);

        return response()->json(['ok' => true]);
    }

    /**
     * Query the notifications created after the seen watermark.
     *
     * @param  array<int, string>  $seenIds
     * @return MorphMany<DatabaseNotification, User>
     */
    private function unseen(User $user, string $seenAt, array $seenIds): MorphMany
    {
        return $user->unreadNotifications()->where(function (Builder $query) use ($seenAt, $seenIds): void {
            $query->where('created_at', '>', $seenAt)
                ->orWhere(function (Builder $query) use ($seenAt, $seenIds): void {
                    $query->where('created_at', $seenAt)->whereNotIn('id', $seenIds);
                });
        });
    }

    /**
     * Build the poller response.
     *
     * @param  array<int, string>  $pending
     */
    private function respond(User $user, ?DatabaseNotification $newest, array $pending, int $unseen): JsonResponse
    {
        return response()->json([
            'notification' => $newest === null ? null : [
                'id' => $newest->id,
                'title' => $newest->data['title'] ?? __('Notification'),
                'url' => route('notifications.index', ['open' => $newest->id]),
            ],
            'pending' => $pending,
            'count' => app(NotificationRepository::class)->countUnread($user),
            'text' => match (true) {
                $unseen > 1 => __('You have :count new notifications.', ['count' => $unseen]),
                $newest !== null => $newest->data['description'] ?? __('You have a new notification.'),
                default => __('You have a new notification.'),
            },
            'linkLabel' => __('Open notification'),
            'closeLabel' => __('Close notification'),
        ]);
    }
}
