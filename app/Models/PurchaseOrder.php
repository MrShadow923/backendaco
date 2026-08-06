<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'purchase_request_id',
        'created_by',
        'supplier_name',
        'order_date',
        'total_amount',
        'status',
        'remarks',
        'receipt_remarks',
    
        'receipt_verified_at',
        'receipt_verified_by',
        'received_at',
        'received_by',
    ];
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'total_amount' => 'decimal:2',
                        
            'receipt_verified_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order) {
            if (empty($order->po_number)) {
                $order->po_number = self::generatePoNumber();
            }
        });
    }

    public static function generatePoNumber(): string
    {
        $year = date('Y');
        $last = static::withTrashed()
            ->where('po_number', 'like', "PO-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) Str::after($last->po_number, "-{$year}-")) + 1 : 1;

        return sprintf('PO-%s-%04d', $year, $seq);
    }

    // Relationships

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PurchaseOrderSignature::class)->orderBy('signed_at');
    }

    public function receipts(): HasMany
    {
        return $this->hasManyThrough(PurchaseOrderItemReceipt::class, PurchaseOrderItem::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receiptVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_verified_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PurchaseRequestActivity::class, 'entity_id')
            ->where('entity_type', 'purchase_order');
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Draft);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PurchaseOrderStatus::PendingFinanceSignature,
            PurchaseOrderStatus::PendingGmSignature,
        ]);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Approved);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Rejected);
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Rejected,
        ]);
    }

    public function hasSignatureForRole(string $role): bool
    {
        return $this->signatures()->where('role', $role)->exists();
    }

    public function getFinanceSignature(): ?PurchaseOrderSignature
    {
        return $this->signatures()->where('role', 'finance_officer')->first();
    }

    public function getGmSignature(): ?PurchaseOrderSignature
    {
        return $this->signatures()->where('role', 'general_manager')->first();
    }
}
