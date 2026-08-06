<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Enums\UserRole;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requested_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PurchaseOrderSignature::class);
    }

    public function isAssetControlOfficer(): bool
    {
        return $this->role === UserRole::AssetControlOfficer;
    }

    public function isPurchaser(): bool
    {
        return $this->role === UserRole::Purchaser;
    }

    public function isFinanceOfficer(): bool
    {
        return $this->role === UserRole::FinanceOfficer;
    }

    public function isGeneralManager(): bool
    {
        return $this->role === UserRole::GeneralManager;
    }
}
