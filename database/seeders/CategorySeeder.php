<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Conference Rooms',  'description' => 'Meeting and seminar rooms'],
            ['name' => 'Lecture Halls',     'description' => 'Large teaching spaces'],
            ['name' => 'Sports Facilities', 'description' => 'Courts and fields'],
            ['name' => 'Computer Labs',     'description' => 'IT-equipped rooms'],
            ['name' => 'Multi-purpose Areas','description' => 'Open halls and event spaces'],
        ];

        foreach ($categories as $c) {
            Category::create($c);
        }
    }
}