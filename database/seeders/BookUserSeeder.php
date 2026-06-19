<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $books = Book::all();

        // اگر کاربری وجود داشت و کتابی هم در دیتابیس بود، ۳ کتاب اول را به او اختصاص بده
        if ($user && $books->count() > 0) {
            $user->purchasedBooks()->syncWithoutDetaching(
                $books->take(3)->pluck('id')
            );
        }
    }
}
