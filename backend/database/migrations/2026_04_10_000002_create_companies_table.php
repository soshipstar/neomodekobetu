<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 企業テーブル - 教室を束ねる上位概念
     * 階層: 企業統括(super_master) > 企業(company) > 教室(classroom) > ユーザー
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // classroomsテーブルに company_id を追加
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
        });

        // usersテーブルに company_id と is_company_admin を追加
        // is_master = 複数企業を統括（既存、最上位）
        // is_company_admin + company_id = 1企業の全教室を管理（新設）
        // 通常admin/staff = classroom_id + classroom_user で教室別
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_company_admin')->default(false)->after('is_master');
            $table->foreignId('company_id')->nullable()->after('classroom_id')->constrained('companies')->nullOnDelete();
        });

        // デフォルト企業を作成し、既存classroomsを紐付ける
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Kiduri',
            'code' => 'default',
            'description' => '既存データ用デフォルト企業',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('classrooms')->update(['company_id' => $companyId]);

        // 既存のis_masterユーザーをデフォルト企業に所属させる
        DB::table('users')
            ->where('is_master', true)
            ->update(['company_id' => $companyId]);
    }

    // 権限の設計:
    // - is_master (マスター管理者): 企業・教室・全ユーザーの作成/編集/削除が可能（最上位）
    // - is_company_admin (企業管理者): 自企業内のユーザー・データの管理は可能だが、教室追加はマスター管理者のみ可
    // - その他 admin (通常管理者): 自教室内のユーザー・データのみ管理可能

    public function down(): void
    {
        // up() で追加したカラムに対応して drop する
        // （is_company_admin と company_id。過去に存在しない is_super_master を
        //   誤って参照していたため down() が常に失敗していた）
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['is_company_admin', 'company_id']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
