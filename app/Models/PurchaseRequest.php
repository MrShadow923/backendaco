<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'requested_by',
        'request_date',
        'purpose',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'request_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseRequest $request) {
            if (empty($request->request_number)) {
                $request->request_number = self::generateRequestNumber();
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        $year = date('Y');
        $last = static::withTrashed()
            ->where('request_number', 'like', "PR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) Str::after($last->request_number, "-{$year}-")) + 1 : 1;

        return sprintf('PR-%s-%04d', $year, $seq);
    }

    // Relationships

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PurchaseRequestActivity::class, 'entity_id')
            ->where('entity_type', 'purchase_request');
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PurchaseRequestStatus::Draft);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', PurchaseRequestStatus::Submitted);
    }

    public function scopeConvertible(Builder $query): Builder
    {
        return $query->where('status', PurchaseRequestStatus::Submitted);
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === PurchaseRequestStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === PurchaseRequestStatus::Submitted;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [
            PurchaseRequestStatus::ConvertedToPO,
            PurchaseRequestStatus::Cancelled,
        ]);
    }

    public function isConvertible(): bool
    {
        return $this->status === PurchaseRequestStatus::Submitted
            && ! $this->purchaseOrder()->exists();
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum('total_amount');
    }
}
