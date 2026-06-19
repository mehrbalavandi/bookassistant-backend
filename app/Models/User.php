<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser; 
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements \Filament\Models\Contracts\FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin', // این خط را هم اضافه کنید تا لاراول اجازه ذخیره آن را بدهد
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
            'password' => 'hashed',
            'is_admin' => 'boolean', // ⚠️ این خط را حتماً اضافه کنید
        ];
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        // فقط اگر کاربر لاگین شده is_admin برابر true داشت، اجازه ورود بده
        return (bool) $this->is_admin === true;
    }  

    // کتاب‌های خریداری شده توسط این کاربر
    public function purchasedBooks()
    {
        return $this->belongsToMany(Book::class, 'book_user')->withTimestamps();
    }    
}
