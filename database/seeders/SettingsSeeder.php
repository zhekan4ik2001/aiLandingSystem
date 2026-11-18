<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::query()->upsert([
            [
                'name' => 'windDirection',
                'description' => 'Направление ветра',
                'value' => '270',
                'type' => 'number',
            ],
            [
                'name' => 'purposeOfRunways',
                'description' => 'Назначение полос',
                'value' => '0',
                'type' => 'select',
            ],
            [
                'name' => 'accelerationOfTime',
                'description' => 'Ускорение времени',
                'value' => '1',
                'type' => 'number',
            ],
            [
                'name' => 'intervalGeneratePlane',
                'description' => 'Интервал появления, сек',
                'value' => '3600',
                'type' => 'number',
            ],
        ], ['name'], ['description', 'type']);
    }
}
