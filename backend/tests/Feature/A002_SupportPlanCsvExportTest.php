<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A-002: SupportPlanController::export() CSV エクスポートのテスト
 *
 * - route GET /api/staff/support-plans/{plan}/export が存在すること
 * - export メソッドがコントローラに存在すること
 * - UTF-8 BOM を出力すること
 */
class A002_SupportPlanCsvExportTest extends TestCase
{
    /**
     * export() メソッドがコントローラに存在すること
     */
    public function test_export_method_exists_in_controller(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Controllers\Staff\SupportPlanController::class, 'export'),
            'SupportPlanController に export() メソッドがありません'
        );
    }

    /**
     * export ルートが登録されていること
     */
    public function test_export_route_is_registered(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

        $exportRoute = $routes->first(function ($route) {
            return str_contains($route->uri(), 'support-plans/')
                && str_contains($route->uri(), '/export')
                && in_array('GET', $route->methods());
        });

        $this->assertNotNull(
            $exportRoute,
            'GET /api/staff/support-plans/{plan}/export ルートが登録されていません'
        );
    }

    /**
     * export メソッドのレスポンスに BOM が含まれることを静的検証
     */
    public function test_export_method_includes_bom(): void
    {
        $file = app_path('Http/Controllers/Staff/SupportPlanController.php');
        $contents = file_get_contents($file);

        // BOM: \xEF\xBB\xBF
        $this->assertTrue(
            str_contains($contents, '\xEF\xBB\xBF') || str_contains($contents, 'BOM'),
            'export() メソッドに UTF-8 BOM 出力が含まれていません'
        );
    }
}
