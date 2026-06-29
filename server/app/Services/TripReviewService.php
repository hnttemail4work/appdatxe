<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\TripReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TripReviewService
{
    public function __construct(
        private readonly GuestTripWatchService $watchlist,
    ) {
    }

    public function submit(
        Booking $booking,
        string $contactPhone,
        string $sentiment,
        ?string $comment = null,
    ): TripReview {
        if (! $booking->matchesContactPhone($contactPhone)) {
            throw new InvalidArgumentException('Số điện thoại không khớp với vé.');
        }

        if (! $this->watchlist->bookingInWatchlist($booking, $contactPhone)) {
            throw new InvalidArgumentException('Không tìm thấy chuyến trong phiên đặt vé của bạn.');
        }

        if ($booking->tripReview) {
            throw new InvalidArgumentException('Bạn đã đánh giá chuyến này rồi.');
        }

        if ($booking->trip_status !== 'completed') {
            throw new InvalidArgumentException('Chuyến chưa hoàn thành, chưa thể đánh giá.');
        }

        if (! $this->watchlist->withinReviewWindow($booking)) {
            throw new InvalidArgumentException('Đã hết thời hạn đánh giá (2 ngày sau khi hoàn thành).');
        }

        if (! in_array($sentiment, [TripReview::SENTIMENT_LIKE, TripReview::SENTIMENT_DISLIKE], true)) {
            throw new InvalidArgumentException('Vui lòng chọn thích hoặc không thích.');
        }

        $comment = $comment !== null ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }

        $booking->loadMissing('schedule');

        return DB::transaction(function () use ($booking, $contactPhone, $sentiment, $comment): TripReview {
            $schedule = $booking->schedule;
            $driverId = $schedule?->driver_id;
            $driverProfile = $driverId
                ? DriverProfile::query()->where('user_id', $driverId)->first()
                : null;

            $review = TripReview::query()->create([
                'booking_id'        => $booking->id,
                'schedule_id'       => $booking->schedule_id,
                'driver_id'         => $driverId,
                'driver_profile_id' => $driverProfile?->id,
                'sentiment'         => $sentiment,
                'comment'           => $comment,
                'contact_phone'     => trim($contactPhone),
            ]);

            if ($driverProfile) {
                if ($sentiment === TripReview::SENTIMENT_LIKE) {
                    $driverProfile->increment('preference_likes');
                } else {
                    $driverProfile->increment('preference_dislikes');
                }
            }

            return $review;
        });
    }

    /** @return array{likes: int, dislikes: int, likes_7d: int, dislikes_7d: int} */
    public function platformTotals(): array
    {
        $since = now()->subDays(7);

        return [
            'likes'        => (int) TripReview::query()->where('sentiment', TripReview::SENTIMENT_LIKE)->count(),
            'dislikes'     => (int) TripReview::query()->where('sentiment', TripReview::SENTIMENT_DISLIKE)->count(),
            'likes_7d'     => (int) TripReview::query()->where('sentiment', TripReview::SENTIMENT_LIKE)->where('created_at', '>=', $since)->count(),
            'dislikes_7d'  => (int) TripReview::query()->where('sentiment', TripReview::SENTIMENT_DISLIKE)->where('created_at', '>=', $since)->count(),
        ];
    }
}
