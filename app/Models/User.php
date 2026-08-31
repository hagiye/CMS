<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = Str::lower(trim($email));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->role !== null;
    }

    public function hasEditorialAccess(): bool
    {
        return $this->role !== null;
    }

    public function canCreateEditorialContent(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Editor], true);
    }

    public function canUpdateEditorialContent(): bool
    {
        return $this->hasEditorialAccess();
    }

    public function canDeleteEditorialContent(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canPublishEditorialContent(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Reviewer], true);
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedNodes()
    {
        return $this->belongsToMany(ContentNode::class, 'bookmarks')->withTimestamps();
    }
}
