<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Create admin, staff, and guardian user accounts.
     */
    public function run(): void
    {
        $classroom1 = Classroom::where('classroom_name', 'きづり教室 本校')->first();
        $classroom2 = Classroom::where('classroom_name', 'きづり教室 第2校')->first();

        if (! $classroom1 || ! $classroom2) {
            $this->command->warn('Classrooms not found. Run ClassroomSeeder first.');

            return;
        }

        // System administrator
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'classroom_id' => $classroom1->id,
                'username' => 'admin',
                'password' => Hash::make('admin1234'),
                'full_name' => 'システム管理者',
                'email' => 'admin@kiduri.example.com',
                'user_type' => 'admin',
                'is_master' => true,
                'is_active' => true,
            ]
        );

        // Master staff for classroom 1
        User::firstOrCreate(
            ['username' => 'staff_master1'],
            [
                'classroom_id' => $classroom1->id,
                'username' => 'staff_master1',
                'password' => Hash::make('staff1234'),
                'full_name' => '山田 太郎',
                'email' => 'yamada@kiduri.example.com',
                'user_type' => 'staff',
                'is_master' => true,
                'is_active' => true,
            ]
        );

        // Regular staff for classroom 1
        User::firstOrCreate(
            ['username' => 'staff1'],
            [
                'classroom_id' => $classroom1->id,
                'username' => 'staff1',
                'password' => Hash::make('staff1234'),
                'full_name' => '佐藤 花子',
                'email' => 'sato@kiduri.example.com',
                'user_type' => 'staff',
                'is_master' => false,
                'is_active' => true,
            ]
        );

        // Staff for classroom 2
        User::firstOrCreate(
            ['username' => 'staff_master2'],
            [
                'classroom_id' => $classroom2->id,
                'username' => 'staff_master2',
                'password' => Hash::make('staff1234'),
                'full_name' => '鈴木 一郎',
                'email' => 'suzuki@kiduri.example.com',
                'user_type' => 'staff',
                'is_master' => true,
                'is_active' => true,
            ]
        );

        // Guardian accounts
        User::firstOrCreate(
            ['username' => 'guardian1'],
            [
                'classroom_id' => $classroom1->id,
                'username' => 'guardian1',
                'password' => Hash::make('guardian1234'),
                'full_name' => '田中 美咲',
                'email' => 'tanaka@example.com',
                'user_type' => 'guardian',
                'is_master' => false,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['username' => 'guardian2'],
            [
                'classroom_id' => $classroom1->id,
                'username' => 'guardian2',
                'password' => Hash::make('guardian1234'),
                'full_name' => '高橋 裕子',
                'email' => 'takahashi@example.com',
                'user_type' => 'guardian',
                'is_master' => false,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['username' => 'guardian3'],
            [
                'classroom_id' => $classroom2->id,
                'username' => 'guardian3',
                'password' => Hash::make('guardian1234'),
                'full_name' => '伊藤 恵子',
                'email' => 'ito@example.com',
                'user_type' => 'guardian',
                'is_master' => false,
                'is_active' => true,
            ]
        );
    }
}
