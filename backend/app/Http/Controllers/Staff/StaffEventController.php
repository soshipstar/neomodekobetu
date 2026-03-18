<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffEventController extends Controller
{
    /**
     * イベント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Event::with('classroom:id,classroom_name');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        // month パラメータ: "YYYY-MM" or separate month/year
        if ($request->filled('month')) {
            $monthParam = $request->month;
            if (str_contains($monthParam, '-')) {
                [$y, $m] = explode('-', $monthParam);
                $query->whereYear('event_date', (int) $y)
                      ->whereMonth('event_date', (int) $m);
            } elseif ($request->filled('year')) {
                $query->whereMonth('event_date', $monthParam)
                      ->whereYear('event_date', $request->year);
            }
        }

        $events = $query->orderBy('event_date')
            ->withCount('registrations')
            ->get();

        $data = $events->map(fn ($e) => [
            'id'                 => $e->id,
            'title'              => $e->event_name,
            'description'        => $e->event_description,
            'date'               => $e->event_date?->format('Y-m-d'),
            'start_time'         => null,
            'end_time'           => null,
            'location'           => null,
            'capacity'           => $e->max_capacity,
            'registration_count' => $e->registrations_count ?? 0,
            'is_published'       => true,
            'event_color'        => $e->event_color,
            'created_at'         => $e->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * イベントを作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title'             => 'required_without:event_name|nullable|string|max:255',
            'event_name'        => 'required_without:title|nullable|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'event_description' => 'nullable|string|max:2000',
            'date'              => 'required_without:event_date|nullable|date',
            'event_date'        => 'required_without:date|nullable|date',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
            'max_capacity'     => 'nullable|integer|min:1',
        ]);

        $event = Event::create([
            'classroom_id'      => $user->classroom_id,
            'created_by'        => $user->id,
            'event_name'        => $validated['title'] ?? $validated['event_name'] ?? '',
            'event_description' => $validated['description'] ?? $validated['event_description'] ?? null,
            'event_date'        => $validated['date'] ?? $validated['event_date'],
            'target_audience'   => $validated['target_audience'] ?? null,
            'event_color'       => $validated['event_color'] ?? null,
            'staff_comment'     => $validated['staff_comment'] ?? null,
            'guardian_message'  => $validated['guardian_message'] ?? null,
            'max_capacity'     => $validated['max_capacity'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $event,
            'message' => 'イベントを登録しました。',
        ], 201);
    }

    /**
     * イベントを更新
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'title'             => 'nullable|string|max:255',
            'event_name'        => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'event_description' => 'nullable|string|max:2000',
            'date'              => 'nullable|date',
            'event_date'        => 'nullable|date',
            'target_audience'   => 'nullable|string|max:100',
            'event_color'       => 'nullable|string|max:20',
            'staff_comment'     => 'nullable|string|max:1000',
            'guardian_message'  => 'nullable|string|max:1000',
            'max_capacity'     => 'nullable|integer|min:1',
        ]);

        $updateData = [];
        if (isset($validated['title']) || isset($validated['event_name'])) {
            $updateData['event_name'] = $validated['title'] ?? $validated['event_name'];
        }
        if (isset($validated['description']) || isset($validated['event_description'])) {
            $updateData['event_description'] = $validated['description'] ?? $validated['event_description'];
        }
        if (isset($validated['date']) || isset($validated['event_date'])) {
            $updateData['event_date'] = $validated['date'] ?? $validated['event_date'];
        }
        foreach (['target_audience', 'event_color', 'staff_comment', 'guardian_message', 'max_capacity'] as $f) {
            if (array_key_exists($f, $validated)) $updateData[$f] = $validated[$f];
        }

        $event->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => $event->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * イベントを削除
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * イベントに参加登録（定員チェック付き）
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'guardian_id' => 'nullable|exists:users,id',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // Capacity check
        if ($event->max_capacity !== null) {
            $currentCount = $event->registrations()
                ->where('status', 'registered')
                ->count();

            if ($currentCount >= $event->max_capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'このイベントは定員に達しています。',
                ], 422);
            }
        }

        $registration = EventRegistration::updateOrCreate(
            [
                'event_id'   => $event->id,
                'student_id' => $validated['student_id'],
            ],
            [
                'guardian_id' => $validated['guardian_id'] ?? null,
                'status'      => 'registered',
                'notes'       => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $registration,
            'message' => '参加登録しました。',
        ], 201);
    }

    /**
     * イベントの参加登録一覧を取得
     */
    public function registrations(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $event->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $registrations = EventRegistration::where('event_id', $event->id)
            ->with([
                'student:id,student_name',
                'guardian:id,full_name',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $registrations,
        ]);
    }
}
