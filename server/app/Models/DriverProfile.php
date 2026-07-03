<?php

namespace App\Models;

use App\Services\DriverPhotoService;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id', 'operator_id', 'driver_code',
        'license_number', 'license_class', 'license_expiry', 'experience_years',
        'status', 'approval_status', 'rejection_reason', 'rejection_reason_at', 'availability_status', 'notes',
        'preference_likes', 'preference_dislikes',
        'cuoc_offer_count', 'cuoc_reject_count', 'cancel_rate_percent',
        'last_lat', 'last_lng', 'last_location_at', 'last_province', 'last_address',
        'missed_trip_strikes', 'missed_trip_locked_at',
        'bank_name', 'bank_account',
        'vehicle_license_plate', 'vehicle_type', 'vehicle_brand', 'vehicle_model', 'vehicle_color', 'vehicle_seats',
        'photo_portrait', 'photo_id_card', 'photo_id_card_back',
        'photo_license_front', 'photo_license_back',
        'photo_vehicle', 'photo_vehicles',
    ];

    protected static function booted(): void
    {
        static::created(function (DriverProfile $profile): void {
            if (empty($profile->driver_code)) {
                $profile->updateQuietly([
                    'driver_code' => self::generateDriverCode($profile->id),
                ]);
            }

            DriverWallet::query()->firstOrCreate(
                ['driver_profile_id' => $profile->id],
                ['balance' => 0],
            );
        });
    }

    public static function generateDriverCode(?int $profileId = null): string
    {
        do {
            $code = 'TX' . str_pad((string) ($profileId ?? random_int(1, 999999)), 6, '0', STR_PAD_LEFT);
            if ($profileId === null) {
                $code = 'TX' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
            }
        } while (self::query()->where('driver_code', $code)->exists());

        return $code;
    }

    public function availabilityLabel(): string
    {
        return match ($this->availability_status ?? 'available') {
            'available' => 'Sẵn sàng',
            'on_trip'   => 'Đang chạy',
            default     => 'Nghỉ',
        };
    }

    /** Trạng thái pill trên hero tài xế — đồng bộ với availability + vị trí. */
    public function heroStatusMeta(bool $onTrip = false): array
    {
        if ($onTrip) {
            return ['key' => 'busy', 'label' => 'Đang chạy chuyến'];
        }

        if (($this->availability_status ?? 'off_duty') === 'off_duty') {
            return ['key' => 'offline', 'label' => 'Tạm nghỉ'];
        }

        if ($this->hasFreshLocation()) {
            return ['key' => 'online', 'label' => 'Sẵn sàng'];
        }

        return ['key' => 'offline', 'label' => 'Cập nhật vị trí để nhận chuyến'];
    }

    /** Một nhãn trạng thái duy nhất (gộp tài khoản + làm việc). */
    public function displayStatusLabel(): string
    {
        if ($this->isMissedTripLocked()) {
            return 'Tạm khóa';
        }

        if ($this->isRejected()) {
            return 'Từ chối';
        }

        if ($this->isPendingApproval()) {
            return 'Chờ duyệt';
        }

        if ($this->status === 'suspended') {
            return 'Tạm ngưng';
        }

        if ($this->status !== 'active') {
            return 'Không hoạt động';
        }

        if ($this->isApproved() && ! $this->isWalletActivated()) {
            return 'Chờ nạp ví';
        }

        return $this->availabilityLabel();
    }

    public function displayStatusColor(): string
    {
        return match ($this->displayStatusLabel()) {
            'Sẵn sàng'           => \App\Support\StatusBadge::SUCCESS,
            'Đang chạy'          => \App\Support\StatusBadge::GOLD,
            'Nghỉ'               => \App\Support\StatusBadge::NEUTRAL,
            'Chờ nạp ví'         => \App\Support\StatusBadge::PENDING,
            'Chờ duyệt'          => \App\Support\StatusBadge::PENDING,
            'Tạm khóa', 'Từ chối', 'Tạm ngưng' => \App\Support\StatusBadge::DANGER,
            default              => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    /** Giá trị form quản lý — map 1 select → status + availability. */
    public function unifiedStatusValue(): string
    {
        if ($this->isMissedTripLocked()) {
            return 'locked';
        }

        if ($this->status === 'suspended') {
            return 'suspended';
        }

        if ($this->status !== 'active') {
            return 'inactive';
        }

        return $this->availability_status ?? 'available';
    }

    protected function casts(): array
    {
        return [
            'license_expiry'   => 'date',
            'experience_years' => 'integer',
            'vehicle_seats'    => 'integer',
            'photo_vehicles'   => 'array',
            'missed_trip_strikes' => 'integer',
            'missed_trip_locked_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'last_location_at' => 'datetime',
            'rejection_reason_at' => 'datetime',
            'cuoc_offer_count' => 'integer',
            'cuoc_reject_count' => 'integer',
            'cancel_rate_percent' => 'float',
        ];
    }

    public function cancelRatePercent(): float
    {
        return round((float) ($this->cancel_rate_percent ?? 0), 1);
    }

    public function cancelRateLabel(): string
    {
        return number_format($this->cancelRatePercent(), 1, ',', '.') . '%';
    }

    public function hasCancelRate(): bool
    {
        return $this->cancelRatePercent() > 0;
    }

    public function hasFreshLocation(int $maxAgeMinutes = 15): bool
    {
        if ($this->last_lat === null || $this->last_lng === null || ! $this->last_location_at) {
            return false;
        }

        return $this->last_location_at->gte(now()->subMinutes($maxAgeMinutes));
    }

    public function photoUrl(string $column): ?string
    {
        return DriverPhotoService::publicUrl($this->{$column});
    }

    public function vehiclePhotoUrls(): array
    {
        return collect($this->photo_vehicles ?? [])
            ->map(fn ($p) => DriverPhotoService::publicUrl($p))
            ->filter()
            ->values()
            ->all();
    }

    public function firstVehiclePhotoUrl(): ?string
    {
        $urls = $this->vehiclePhotoUrls();

        return $urls[0] ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function wallet()
    {
        return $this->hasOne(DriverWallet::class);
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isPendingApproval(): bool
    {
        $this->loadMissing('user');

        return $this->user
            && $this->user->role === 'driver'
            && $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /** Khóa họ tên / SĐT sau duyệt — giống mã tài xế, chỉ xem không sửa. */
    public function contactFieldsLocked(): bool
    {
        if ($this->isPendingApproval() || $this->isRejected()) {
            return false;
        }

        if ($this->isApproved()) {
            return true;
        }

        return in_array($this->status, ['active', 'suspended'], true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending')
            ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
    }

    /** Hồ sơ chờ duyệt mà quản lý được xem/duyệt (của mình hoặc chưa gán đơn vị). */
    public function scopePendingForOperator($query, int $operatorId)
    {
        return $query->pendingApproval()
            ->where(function ($q) use ($operatorId): void {
                $q->where('operator_id', $operatorId)
                    ->orWhereNull('operator_id');
            });
    }

    /** Tài xế thuộc quản lý + hồ sơ đăng ký mới chờ duyệt. */
    public function scopeForOperatorManagement($query, int $operatorId)
    {
        return $query->where(function ($q) use ($operatorId): void {
            $q->where('operator_id', $operatorId)
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('operator_id')
                    ->where('approval_status', 'pending'));
        });
    }

    /** Tài xế thuộc quản lý (ví, nạp tiền, kết chuyến). */
    public function scopeManagedByOperator($query, int $operatorId)
    {
        return $query->where('operator_id', $operatorId);
    }

    public static function pendingCountForOperator(int $operatorId): int
    {
        return (int) self::query()->pendingForOperator($operatorId)->count();
    }

    /** Tài xế đã duyệt, ví đã kích hoạt và có thể nhận chuyến */
    public function isOperational(): bool
    {
        $this->loadMissing('user');

        return $this->status === 'active'
            && $this->user
            && $this->user->status === 'active'
            && $this->missed_trip_locked_at === null
            && $this->isApproved()
            && $this->isWalletActivated();
    }

    public function isWalletActivated(): bool
    {
        $this->loadMissing('wallet');

        return (bool) ($this->wallet?->wallet_activated_at);
    }

    /** Ảnh CCCD, bằng lái, chân dung — khóa sau khi admin/QL duyệt. */
    public const IDENTITY_PHOTO_FIELDS = [
        'photo_portrait',
        'photo_id_card',
        'photo_id_card_back',
        'photo_license_front',
        'photo_license_back',
    ];

    public function identityPhotosLocked(): bool
    {
        if ($this->isPendingApproval() && $this->status === 'inactive') {
            return false;
        }

        return in_array($this->status, ['active', 'suspended'], true);
    }

    public function scopeOperational($query)
    {
        return $query
            ->where('driver_profiles.status', 'active')
            ->whereNull('driver_profiles.missed_trip_locked_at')
            ->whereHas('user', fn ($q) => $q->where('role', 'driver')->where('status', 'active'));
    }

    public function isMissedTripLocked(): bool
    {
        return $this->missed_trip_locked_at !== null;
    }

    public function missedTripStrikeLabel(): string
    {
        $strikes = (int) $this->missed_trip_strikes;

        if ($this->isMissedTripLocked()) {
            return 'Khóa do bỏ lỡ ' . \App\Services\DriverMissedTripService::STRIKE_LIMIT . ' chuyến';
        }

        if ($strikes > 0) {
            return $strikes . '/' . \App\Services\DriverMissedTripService::STRIKE_LIMIT . ' lần bỏ lỡ chuyến';
        }

        return '';
    }

    public function documentsComplete(): bool
    {
        $required = ['photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_license_front'];

        foreach ($required as $col) {
            if (empty($this->{$col})) {
                return false;
            }
        }

        return count($this->photo_vehicles ?? []) > 0;
    }

    /** Danh sách kiểm tra độ đầy đủ thông tin chữ (đồng bộ users + driver_profiles). */
    public function profileFieldChecklist(): array
    {
        $this->loadMissing('user');
        $user = $this->user;

        return [
            ['key' => 'name', 'label' => 'Họ và tên', 'ok' => filled($user?->name), 'group' => 'contact', 'required' => true],
            ['key' => 'phone', 'label' => 'Số điện thoại', 'ok' => filled($user?->phone), 'group' => 'contact', 'required' => true],
            ['key' => 'vehicle_license_plate', 'label' => 'Biển số xe', 'ok' => filled($this->vehicle_license_plate), 'group' => 'vehicle', 'required' => true],
            ['key' => 'vehicle_type', 'label' => 'Loại xe', 'ok' => filled($this->vehicle_type), 'group' => 'vehicle', 'required' => true],
            ['key' => 'vehicle_seats', 'label' => 'Số ghế', 'ok' => $this->vehicle_seats !== null && $this->vehicle_seats > 0, 'group' => 'vehicle', 'required' => true],
            ['key' => 'bank_name', 'label' => 'Tên ngân hàng', 'ok' => filled($this->bank_name), 'group' => 'bank', 'required' => true],
            ['key' => 'bank_account', 'label' => 'Số tài khoản', 'ok' => filled($this->bank_account), 'group' => 'bank', 'required' => true],
        ];
    }

    public function hasValidLicenseNumber(): bool
    {
        if (! filled($this->license_number)) {
            return false;
        }

        return ! in_array($this->license_number, ['Chưa cập nhật', 'N/A', '-'], true);
    }

    /**
     * Trạng thái từng nhóm: complete | partial | empty
     *
     * @return array<string, array{state: string, filled: int, total: int, required_filled: int, required_total: int}>
     */
    public function sectionProgress(): array
    {
        $items = $this->profileFieldChecklist();

        $groups = [
            'contact' => ['label' => 'Liên hệ'],
            'vehicle' => ['label' => 'Thông tin xe'],
            'bank'    => ['label' => 'Ngân hàng'],
        ];

        $result = [];

        foreach ($groups as $group => $meta) {
            $groupItems = array_values(array_filter($items, fn (array $i) => $i['group'] === $group));
            $filled = count(array_filter($groupItems, fn (array $i) => $i['ok']));
            $total = count($groupItems);

            $requiredItems = array_filter($groupItems, fn (array $i) => $i['required']);
            $requiredFilled = count(array_filter($requiredItems, fn (array $i) => $i['ok']));
            $requiredTotal = count($requiredItems);

            $optionalFilled = $filled - $requiredFilled;
            $optionalTotal = $total - $requiredTotal;

            // Đủ bắt buộc → tick xanh; thiếu bắt buộc nhưng có dữ liệu khác → partial
            if ($requiredTotal > 0 && $requiredFilled === $requiredTotal) {
                $state = 'complete';
            } elseif ($filled > 0) {
                $state = 'partial';
            } else {
                $state = 'empty';
            }

            $result[$group] = [
                'state'           => $state,
                'filled'          => $filled,
                'total'           => $total,
                'required_filled' => $requiredFilled,
                'required_total'  => $requiredTotal,
                'optional_filled' => $optionalFilled,
                'optional_total'  => $optionalTotal,
            ];
        }

        $docOk = $this->documentsComplete();
        $result['documents'] = [
            'state'           => $docOk ? 'complete' : ($this->hasAnyDocumentPhoto() ? 'partial' : 'empty'),
            'filled'          => $this->documentPhotoCount(),
            'total'           => 5,
            'required_filled' => $docOk ? 5 : $this->documentPhotoCount(),
            'required_total'  => 5,
        ];

        return $result;
    }

    public function hasRejectionNote(): bool
    {
        return filled($this->rejection_reason);
    }

    public function hasAnyDocumentPhoto(): bool
    {
        foreach (['photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_license_front'] as $col) {
            if (filled($this->{$col})) {
                return true;
            }
        }

        return count($this->photo_vehicles ?? []) > 0;
    }

    public function documentPhotoCount(): int
    {
        $count = 0;
        foreach (['photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_license_front'] as $col) {
            if (filled($this->{$col})) {
                $count++;
            }
        }
        if (count($this->photo_vehicles ?? []) > 0) {
            $count++;
        }

        return $count;
    }

    /** @return list<string> */
    public function missingFieldLabels(): array
    {
        $missing = array_values(array_map(
            fn (array $item) => $item['label'],
            array_filter(
                $this->profileFieldChecklist(),
                fn (array $item) => ! $item['ok'] && ($item['required'] ?? true)
            )
        ));

        if (! $this->documentsComplete()) {
            $missing[] = 'Ảnh giấy tờ & xe';
        }

        return $missing;
    }

    /** @return list<string> */
    public function missingOptionalFieldLabels(): array
    {
        $missing = array_values(array_map(
            fn (array $item) => $item['label'],
            array_filter(
                $this->profileFieldChecklist(),
                fn (array $item) => ! $item['ok'] && ! ($item['required'] ?? true)
            )
        ));

        return array_values(array_unique($missing));
    }

    public function profileDataComplete(): bool
    {
        return $this->missingFieldLabels() === [];
    }

    public function completenessPercent(): int
    {
        $sections = $this->sectionProgress();
        $weights = [
            'documents' => 45,
            'contact'   => 25,
            'vehicle'   => 20,
            'bank'      => 10,
        ];

        $score = 0;
        foreach ($weights as $key => $weight) {
            $sec = $sections[$key] ?? ['state' => 'empty'];
            $score += match ($sec['state']) {
                'complete' => $weight,
                'partial'  => (int) round($weight * 0.5),
                default    => 0,
            };
        }

        return min(100, $score);
    }

    /** @return array<string, bool> */
    public function sectionComplete(): array
    {
        $progress = $this->sectionProgress();

        return array_map(fn (array $s) => $s['state'] === 'complete', $progress);
    }

    public function vehicleLabel(): string
    {
        if (! $this->vehicle_license_plate) {
            return '—';
        }

        $parts = array_filter([
            $this->vehicle_license_plate,
            $this->vehicle_type ? ucfirst($this->vehicle_type) : null,
            $this->vehicle_brand,
            $this->vehicle_model,
        ]);

        return implode(', ', $parts);
    }

    public function accountStatusLabel(): string
    {
        $this->loadMissing('user');

        if ($this->isMissedTripLocked()) {
            return 'Tạm khóa (bỏ lỡ chuyến)';
        }

        if ($this->isRejected()) {
            return 'Từ chối';
        }

        if ($this->isPendingApproval()) {
            return 'Chờ duyệt';
        }

        $status = $this->user?->status ?? $this->status;

        return match ($status) {
            'active'    => 'Hoạt động',
            'suspended' => 'Tạm ngưng',
            default     => 'Không hoạt động',
        };
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'driver_id', 'user_id');
    }
}
