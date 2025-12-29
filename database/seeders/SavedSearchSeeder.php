<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SavedSearch;

class SavedSearchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SavedSearch::updateOrCreate(
            ['id' => 1],
            [
                'area' => 'Manchester, Greater Manchester',
                'updates_url' => 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Manchester%2C+Greater+Manchester&useLocationIdentifier=true&locationIdentifier=REGION%5E904&buy=For+sale&radius=0.0&_includeSSTC=on',
            ]
        );
        
        echo "Manchester saved search created/updated successfully!\n";
    }
}
