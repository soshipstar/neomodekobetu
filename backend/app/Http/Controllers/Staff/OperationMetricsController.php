<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\OperationMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationMetricsController extends Controller
{
    public function __construct(private readonly OperationMetricsService $service) {}

    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $classroomId = $request->user()->classroom_id;
        return response()->json([
            'data' => $this->service->monthly($classroomId, $request->string('year_month')->toString()),
        ]);
    }
}
