<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->branch_id) {
                if (! $user->company_id) {
                    throw new InvalidArgumentException('User company is required when assigning a branch.');
                }

                $branch = Branch::query()->find($user->branch_id);

                if (! $branch || ($user->company_id && $branch->company_id !== $user->company_id)) {
                    throw new InvalidArgumentException('User branch must belong to the same company.');
                }
            }

            if ($user->warehouse_id) {
                if (! $user->company_id) {
                    throw new InvalidArgumentException('User company is required when assigning a warehouse.');
                }

                $warehouse = Warehouse::query()->find($user->warehouse_id);

                if (! $warehouse || ($user->company_id && $warehouse->company_id !== $user->company_id)) {
                    throw new InvalidArgumentException('User warehouse must belong to the same company.');
                }

                if ($user->branch_id && $warehouse->branch_id !== $user->branch_id) {
                    throw new InvalidArgumentException('User warehouse must belong to the assigned branch.');
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdPurchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'created_by');
    }

    public function createdSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(CashierShift::class, 'cashier_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($panel->getId() !== 'admin') {
            return true;
        }

        return $this->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
