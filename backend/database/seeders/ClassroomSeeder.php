<?php

namespace Database\Seeders;

use App\Models\Classroom;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Create sample classrooms.
     */
    public function run(): void
    {
        $classrooms = [
            [
                'classroom_name' => 'きづり教室 本校',
                'address' => '大阪府東大阪市吉田1-2-3',
                'phone' => '06-1234-5678',
                'settings' => json_encode([
                    'capacity' => 10,
                    'service_type' => '放課後等デイサービス',
                ]),
                'is_active' => true,
            ],
            [
                'classroom_name' => 'きづり教室 第2校',
                'address' => '大阪府東大阪市吉田4-5-6',
                'phone' => '06-2345-6789',
                'settings' => json_encode([
                    'capacity' => 10,
                    'service_type' => '放課後等デイサービス',
                ]),
                'is_active' => true,
            ],
            [
                'classroom_name' => 'きづり教室 第3校',
                'address' => '大阪府東大阪市鴻池7-8-9',
                'phone' => '06-3456-7890',
                'settings' => json_encode([
                    'capacity' => 10,
                    'service_type' => '児童発達支援',
                ]),
                'is_active' => true,
            ],
        ];

        foreach ($classrooms as $data) {
            Classroom::firstOrCreate(
                ['classroom_name' => $data['classroom_name']],
                $data
            );
        }
    }
}
