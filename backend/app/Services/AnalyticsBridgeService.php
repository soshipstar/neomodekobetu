<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsBridgeService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.analytics.url', 'http://python-analytics:8000'), '/');
    }

    /**
     * Get student growth analysis from the Python analytics service.
     *
     * Returns trend data for 5 developmental domains over time.
     *
     * @param  int  $studentId
     * @return array
     */
    public function getStudentGrowth(int $studentId): array
    {
        return $this->request('GET', "/api/analytics/student/{$studentId}/growth");
    }

    /**
     * Get facility evaluation analysis for a classroom.
     *
     * Aggregates and analyzes facility evaluation responses.
     *
     * @param  int  $classroomId
     * @return array
     */
    public function getFacilityEvaluation(int $classroomId): array
    {
        return $this->request('GET', "/api/analytics/classroom/{$classroomId}/facility-evaluation");
    }

    /**
     * Get attendance statistics analysis for a classroom.
     *
     * @param  int  $classroomId
     * @return array
     */
    public function getAttendanceStats(int $classroomId): array
    {
        return $this->request('GET', "/api/analytics/classroom/{$classroomId}/attendance");
    }

    /**
     * Make an HTTP request to the Python analytics service.
     *
     * @param  string  $method  HTTP method
     * @param  string  $path  API path
     * @param  array  $data  Request body (for POST/PUT)
     * @return array
     *
     * @throws \RuntimeException  When the analytics service is unreachable or returns an error
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Internal-Service' => config('services.analytics.api_key', ''),
                ])
                ->$method($url, $data);

            if ($response->failed()) {
                Log::error('Analytics service request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "Analytics service returned HTTP {$response->status()}"
                );
            }

            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Analytics service connection failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Analytics service is unavailable: ' . $e->getMessage()
            );
        }
    }
}
