<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // AI学習基盤の参照データ(冪等)。本番では db:seed --class= で個別投入も可。
            ConsentDefinitionSeeder::class,
            AiEditReasonCategorySeeder::class,
            ProgramCategorySeeder::class,
            ClassroomSeeder::class,
            UserSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
