<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'Conference Rooms'    => [['Room 101', 20], ['Room 102', 15]],
            'Lecture Halls'       => [['Dewan Kuliah A', 120], ['Dewan Kuliah B', 100]],
            'Sports Facilities'   => [['Basketball Court', 30], ['Badminton Court', 12]],
            'Computer Labs'       => [['Computer Lab 1', 40], ['Computer Lab 2', 40]],
            'Multi-purpose Areas' => [['Dewan Serbaguna', 300]],
        ];

        foreach ($map as $categoryName => $items) {
            $category = Category::where('name', $categoryName)->first();
            foreach ($items as [$name, $capacity]) {
                Facility::create([
                    'category_id' => $category->id,
                    'name'        => $name,
                    'capacity'    => $capacity,
                    'is_active'   => true,
                ]);
            }
        }
    }
}