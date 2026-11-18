<?php

namespace Database\Seeders;

use App\Models\Point;
use Illuminate\Database\Seeder;

class PointsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $points = [
            // Существующие точки (id 1-15) - arrival
            ['AKARU', 60.186667, 28.631667, 15000, 'input', 'arrival'],
            ['BEPOL', 60.060833, 32.293056, 16000, 'input', 'arrival'],
            ['GENPA', 59.106111, 31.811944, 16000, 'input', 'arrival'],
            ['KOLET', 58.999722, 29.186944, 15000, 'input', 'arrival'],
            ['OBANA', 59.278889, 28.488333, 15000, 'input', 'arrival'],
            ['ARNAT', 59.802139, 30.765056, 3000, 'intermediate', 'arrival'],
            ['NANEB', 59.935528, 29.844806, 3000, 'intermediate', 'arrival'],
            ['DIKOM', 59.793556, 29.760389, 3000, 'intermediate', 'arrival'],
            ['OBARI', 59.666417, 30.679194, 3000, 'intermediate', 'arrival'],
            ['LEPTO', 59.852583, 29.954667, 2500, 'intermediate', 'arrival'],
            ['GEBKA', 59.841778, 29.933556, 2500, 'intermediate', 'arrival'],
            ['BIPRI', 59.747917, 30.565639, 2500, 'intermediate', 'arrival'],
            ['MADID', 59.758361, 30.587500, 2500, 'intermediate', 'arrival'],
            ['ВПП 10R/28L', 59.805341, 30.274732, 79, 'runway', 'arrival'],
            ['ВПП 10L/28R', 59.80083333, 30.25833333, 79, 'runway', 'arrival'],
            
            // Новые точки из task.txt (id 16-47) - для SID (вылеты), используем departure
            // Официальные стартовые точки SID (пороги ВПП)
            ['LI719', 59.82950000, 30.11166667, NULL, 'runway', 'departure'],  // старт SID 1A, RW28R
            ['LI785', 59.81686111, 30.10369444, NULL, 'runway', 'departure'],  // старт SID 1B, RW28L
            ['LI783', 59.78116667, 30.43694444, NULL, 'runway', 'departure'],  // старт SID 1D, RW10L
            ['LI784', 59.76844444, 30.42944444, NULL, 'runway', 'departure'],  // старт SID 1C, RW10R
            
            // Промежуточные точки
            ['LI768', 59.83633333, 29.97077778, NULL, 'intermediate', 'departure'],
            ['LI714', 59.94341667, 30.03261111, NULL, 'intermediate', 'departure'],
            ['LI715', 59.92108333, 30.18500000, NULL, 'intermediate', 'departure'],
            ['LI716', 59.88788889, 30.33286111, 8000, 'intermediate', 'departure'],
            ['LI717', 59.87741667, 30.68586111, 13000, 'intermediate', 'departure'],
            ['LI709', 59.84905556, 29.97808333, NULL, 'intermediate', 'departure'],
            ['LI710', 59.73286111, 29.91138889, NULL, 'intermediate', 'departure'],
            ['LI711', 59.69655556, 30.15825000, 7000, 'intermediate', 'departure'],
            ['LI712', 59.69344444, 30.33977778, 10000, 'intermediate', 'departure'],
            ['LI713', 59.65583333, 30.69180556, 14000, 'intermediate', 'departure'],
            ['LI718', 59.92361111, 29.75463889, NULL, 'intermediate', 'departure'],
            ['LI720', 60.14241667, 29.77961111, 12000, 'intermediate', 'departure'],
            ['LI707', 59.69438889, 30.80752778, 6000, 'intermediate', 'departure'],
            ['LI708', 59.35200000, 30.82594444, 10000, 'intermediate', 'departure'],
            ['LI702', 59.85525000, 30.62605556, NULL, 'intermediate', 'departure'],
            ['LI703', 59.87811111, 30.47425000, NULL, 'intermediate', 'departure'],
            ['LI704', 59.86230556, 30.30447222, NULL, 'intermediate', 'departure'],
            ['LI705', 59.83122222, 30.13327778, 11000, 'intermediate', 'departure'],
            ['LI706', 59.84538889, 30.12558333, 11000, 'intermediate', 'departure'],
            ['LI701', 59.76113889, 30.57000000, NULL, 'intermediate', 'departure'],
            ['LI767', 59.74838889, 30.56241667, NULL, 'intermediate', 'departure'],
            
            // Выходные точки
            ['PETAP', 60.33888889, 29.80222222, 12000, 'output', 'departure'],
            ['RELNI', 60.14722222, 31.50805556, 13000, 'output', 'departure'],
            ['KERIS', 59.81555556, 32.33833333, 13000, 'output', 'departure'],
            ['SUGIN', 59.53194444, 32.11000000, 15000, 'output', 'departure'],
            ['RUMOL', 59.12027778, 30.83805556, 15000, 'output', 'departure'],
            ['TURAL', 59.62305556, 29.02444444, 12000, 'output', 'departure'],
            ['UTETA', 59.73472222, 29.01722222, 12000, 'output', 'departure'],
        ];

        foreach ($points as [$name, $lat, $lon, $altitude, $type, $use]) {
            Point::query()->updateOrCreate(
                ['name' => $name],
                [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'altitude_feet' => $altitude,
                    'type' => $type,
                    'use' => $use,
                ]
            );
        }
    }
}
