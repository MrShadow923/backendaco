<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestFormat extends Model
{
    protected $fillable = [
        'name',
        'format_data',
        'status',
        'created_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'format_data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
