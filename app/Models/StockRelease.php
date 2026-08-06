<?php

namespace App\Models;

use App\Enums\StockReleaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockRelease extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'department_id',
        'revenue_center_id',
        'status',
        'released_at',
        'released_by',
        'notes',
        'total_quantity',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockReleaseStatus::class,
            'released_at' => 'datetime',
            'total_quantity' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockRelease $release) {
            if (empty($release->reference_number)) {
                $release->reference_number = self::generateReferenceNumber();
            }
        });
    }

    public static function generateReferenceNumber(): string
    {
        $year = date('Y');
        $last = static::withTrashed()
            ->where('reference_number', 'like', "SR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) Str::after($last->reference_number, "-{$year}-")) + 1 : 1;

        return sprintf('SR-%s-%04d', $year, $seq);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function revenueCenter(): BelongsTo
    {
        return $this->belongsTo(RevenueCenter::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReleaseItem::class);
    }

    public function isReleased(): bool
    {
        return $this->status === StockReleaseStatus::Released;
    }

    public function canEdit(): bool
    {
        return $this->status === StockReleaseStatus::Draft;
    }
}
