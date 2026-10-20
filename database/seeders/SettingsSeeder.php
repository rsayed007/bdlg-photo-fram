<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\HomeContent;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::where('key', HomeContent::KEY)->doesntExist()) {
            Setting::put(HomeContent::KEY, HomeContent::defaults());
        }
    }
}
