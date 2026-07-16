<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'avatar_path', 'password', 'terms_accepted_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    protected $attributes = [
        'role' => 'customer',
    ];

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasOne<Cart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'actor_id');
    }

    public function registeredFiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class, 'registered_by');
    }

    public function annulledFiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class, 'annulled_by');
    }

    public function fiscalDocumentDeliveries(): HasMany
    {
        return $this->hasMany(FiscalDocumentDelivery::class, 'attempted_by');
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', UserRole::Customer->value);
    }

    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', UserRole::Admin->value);
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return 'VN';
        }

        $initials = $parts->count() === 1
            ? Str::substr($parts->first(), 0, 2)
            : Str::substr($parts->first(), 0, 1).Str::substr($parts->last(), 0, 1);

        return Str::upper($initials);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'terms_accepted_at' => 'datetime',
        ];
    }
}
