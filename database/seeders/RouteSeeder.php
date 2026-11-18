<?php

namespace Database\Seeders;

use App\Models\Route;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $routes = [
            // Существующие маршруты (id 1-20)
            ['BEPOL - NANEB - LEPTO - ВПП 10L/28R', [2, 7, 10, 15]],
            ['BEPOL - NANEB - GEBKA - ВПП 10R/28L', [2, 7, 11, 14]],
            ['BEPOL - ARNAT - MADID - ВПП 10L/28R', [2, 6, 13, 15]],
            ['BEPOL - ARNAT - BIPRI - ВПП 10R/28L', [2, 6, 12, 14]],
            ['GENPA - DIKOM - LEPTO - ВПП 10L/28R', [3, 8, 10, 15]],
            ['GENPA - DIKOM - GEBKA - ВПП 10R/28L', [3, 8, 11, 14]],
            ['GENPA - OBARI - MADID - ВПП 10L/28R', [3, 9, 13, 15]],
            ['GENPA - OBARI - BIPRI - ВПП 10R/28L', [3, 9, 12, 14]],
            ['OBANA - DIKOM - LEPTO - ВПП 10L/28R', [5, 8, 10, 15]],
            ['OBANA - DIKOM - GEBKA - ВПП 10R/28L', [5, 8, 11, 14]],
            ['KOLET - DIKOM - LEPTO - ВПП 10L/28R', [4, 8, 10, 15]],
            ['KOLET - DIKOM - GEBKA - ВПП 10R/28L', [4, 8, 11, 14]],
            ['OBANA - OBARI - MADID - ВПП 10L/28R', [5, 9, 13, 15]],
            ['OBANA - OBARI - BIPRI - ВПП 10R/28L', [5, 9, 12, 14]],
            ['KOLET - OBARI - MADID - ВПП 10L/28R', [4, 9, 13, 15]],
            ['KOLET - OBARI - BIPRI - ВПП 10R/28L', [4, 9, 12, 14]],
            ['AKARU - NANEB - LEPTO - ВПП 10L/28R', [1, 7, 10, 15]],
            ['AKARU - NANEB - GEBKA - ВПП 10R/28L', [1, 7, 11, 14]],
            ['AKARU - ARNAT - MADID - ВПП 10L/28R', [1, 6, 13, 15]],
            ['AKARU - ARNAT - BIPRI - ВПП 10R/28L', [1, 6, 12, 14]],
            
            // Новые маршруты из task.txt (id 21-43)
            ['KERIS 1A', [14, 16, 25, 21, 22, 23, 24, 43]],
            ['RELNI 1A', [14, 16, 25, 21, 22, 23, 24, 42]],
            ['RUMOL 1A', [14, 16, 25, 26, 27, 28, 29, 45]],
            ['SUGIN 1A', [14, 16, 25, 26, 27, 28, 29, 44]],
            // ВПП 28L (старт: LI785)
            ['KERIS 1B', [14, 17, 20, 21, 22, 23, 24, 43]],
            ['RELNI 1B', [14, 17, 20, 21, 22, 23, 24, 42]],
            ['RUMOL 1B', [14, 17, 20, 26, 27, 28, 29, 45]],
            ['SUGIN 1B', [14, 17, 20, 26, 27, 28, 29, 44]],
            ['PETAP 1B', [14, 17, 20, 30, 31, 41]],
            ['UTETA 1B', [14, 17, 20, 47]],
            ['TURAL 1B', [14, 17, 20, 46]],
            // ВПП 10R (старт: LI784)
            ['KERIS 1C', [14, 19, 40, 43]],
            ['RELNI 1C', [14, 19, 40, 42]],
            ['RUMOL 1C', [14, 19, 40, 32, 33, 45]],
            ['SUGIN 1C', [14, 19, 40, 44]],
            ['PETAP 1C', [14, 19, 40, 34, 35, 36, 38, 41]],
            ['TURAL 1C', [14, 19, 40, 34, 35, 36, 37, 46]],
            ['UTETA 1C', [14, 19, 40, 34, 35, 36, 38, 47]],
            // ВПП 10L (старт: ВПП 10L/28R → LI783)
            ['RUMOL 1D', [15, 18, 39, 32, 33, 45]],
            ['SUGIN 1D', [15, 18, 39, 44]],
            ['PETAP 1D', [15, 18, 39, 34, 35, 36, 38, 41]],
            ['TURAL 1D', [15, 18, 39, 37, 46]],
            ['UTETA 1D', [15, 18, 39, 34, 35, 36, 38, 47]],
        ];

        foreach ($routes as [$name, $pointIds]) {
            Route::query()->updateOrCreate(
                ['name' => $name],
                ['point_ids' => $pointIds]
            );
        }
    }
}
