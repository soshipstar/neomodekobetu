<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CompanyInternship;
use App\Models\JobApplication;
use App\Models\JobPlacement;
use App\Models\JobPlacementContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 就労移行支援フル機能 API。
 * - 求職活動 (job_applications)
 * - 企業実習 (company_internships)
 * - 就職後定着 (job_placements + contacts)
 */
class TransitionSupportController extends Controller
{
    // =========================================================================
    // Job Applications
    // =========================================================================

    public function indexApplications(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $items = JobApplication::query()
            ->where('classroom_id', $classroomId)
            ->orderByDesc('application_date')
            ->with('student:id,student_name')
            ->limit(200)
            ->get();
        return response()->json(['data' => $items]);
    }

    public function storeApplication(Request $request): JsonResponse
    {
        $validated = $this->validateApplication($request);
        $validated['classroom_id'] = $request->user()->classroom_id;
        $validated['created_by'] = $request->user()->id;
        $app = JobApplication::create($validated);
        return response()->json(['data' => $app->load('student:id,student_name')], 201);
    }

    public function updateApplication(Request $request, JobApplication $application): JsonResponse
    {
        $this->authorizeClassroom($request, $application->classroom_id);
        $validated = $this->validateApplication($request, true);
        $application->update($validated);
        return response()->json(['data' => $application->fresh('student:id,student_name')]);
    }

    public function destroyApplication(Request $request, JobApplication $application): JsonResponse
    {
        $this->authorizeClassroom($request, $application->classroom_id);
        $application->delete();
        return response()->json(['data' => null]);
    }

    // =========================================================================
    // Company Internships
    // =========================================================================

    public function indexInternships(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $items = CompanyInternship::query()
            ->where('classroom_id', $classroomId)
            ->orderByDesc('start_date')
            ->with('student:id,student_name')
            ->limit(200)
            ->get();
        return response()->json(['data' => $items]);
    }

    public function storeInternship(Request $request): JsonResponse
    {
        $validated = $this->validateInternship($request);
        $validated['classroom_id'] = $request->user()->classroom_id;
        $validated['created_by'] = $request->user()->id;
        $item = CompanyInternship::create($validated);
        return response()->json(['data' => $item->load('student:id,student_name')], 201);
    }

    public function updateInternship(Request $request, CompanyInternship $internship): JsonResponse
    {
        $this->authorizeClassroom($request, $internship->classroom_id);
        $validated = $this->validateInternship($request, true);
        $internship->update($validated);
        return response()->json(['data' => $internship->fresh('student:id,student_name')]);
    }

    public function destroyInternship(Request $request, CompanyInternship $internship): JsonResponse
    {
        $this->authorizeClassroom($request, $internship->classroom_id);
        $internship->delete();
        return response()->json(['data' => null]);
    }

    // =========================================================================
    // Job Placements (就職後定着)
    // =========================================================================

    public function indexPlacements(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $items = JobPlacement::query()
            ->where('classroom_id', $classroomId)
            ->orderByDesc('start_date')
            ->with(['student:id,student_name', 'contacts'])
            ->limit(200)
            ->get();
        return response()->json(['data' => $items]);
    }

    public function showPlacement(Request $request, JobPlacement $placement): JsonResponse
    {
        $this->authorizeClassroom($request, $placement->classroom_id);
        return response()->json([
            'data' => $placement->load(['student:id,student_name', 'contacts']),
        ]);
    }

    public function storePlacement(Request $request): JsonResponse
    {
        $validated = $this->validatePlacement($request);
        $validated['classroom_id'] = $request->user()->classroom_id;
        $validated['created_by'] = $request->user()->id;
        $item = JobPlacement::create($validated);
        return response()->json(['data' => $item->load(['student:id,student_name', 'contacts'])], 201);
    }

    public function updatePlacement(Request $request, JobPlacement $placement): JsonResponse
    {
        $this->authorizeClassroom($request, $placement->classroom_id);
        $validated = $this->validatePlacement($request, true);
        $placement->update($validated);
        return response()->json(['data' => $placement->fresh(['student:id,student_name', 'contacts'])]);
    }

    public function destroyPlacement(Request $request, JobPlacement $placement): JsonResponse
    {
        $this->authorizeClassroom($request, $placement->classroom_id);
        $placement->delete();
        return response()->json(['data' => null]);
    }

    public function storeContact(Request $request, JobPlacement $placement): JsonResponse
    {
        $this->authorizeClassroom($request, $placement->classroom_id);
        $validated = $request->validate([
            'contact_date'       => 'required|date',
            'contact_type'       => 'required|string|max:30',
            'contact_with'       => 'nullable|string|max:100',
            'content'            => 'required|string|max:5000',
            'issues_raised'      => 'nullable|string|max:5000',
            'actions_taken'      => 'nullable|string|max:5000',
            'satisfaction_score' => 'nullable|integer|min:1|max:5',
            'attendance_rate'    => 'nullable|integer|min:0|max:100',
        ]);
        $validated['job_placement_id'] = $placement->id;
        $validated['created_by'] = $request->user()->id;
        $contact = JobPlacementContact::create($validated);

        // 次回フォローを 1 ヶ月後に自動更新
        $placement->update([
            'next_followup_date' => \Carbon\Carbon::parse($validated['contact_date'])->addMonth()->toDateString(),
        ]);

        return response()->json(['data' => $contact], 201);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function validateApplication(Request $request, bool $partial = false): array
    {
        $rules = [
            'student_id'      => ($partial ? 'sometimes|' : 'required|') . 'integer|exists:students,id',
            'company_name'    => ($partial ? 'sometimes|' : 'required|') . 'string|max:255',
            'industry'        => 'nullable|string|max:100',
            'job_title'       => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:50',
            'application_date' => ($partial ? 'sometimes|' : 'required|') . 'date',
            'source'          => 'nullable|string|max:50',
            'status'          => 'nullable|string|max:30',
            'interview_date'  => 'nullable|date',
            'result_date'     => 'nullable|date',
            'result_notes'    => 'nullable|string|max:5000',
            'feedback'        => 'nullable|string|max:5000',
        ];
        return $request->validate($rules);
    }

    private function validateInternship(Request $request, bool $partial = false): array
    {
        $rules = [
            'student_id'         => ($partial ? 'sometimes|' : 'required|') . 'integer|exists:students,id',
            'company_name'       => ($partial ? 'sometimes|' : 'required|') . 'string|max:255',
            'contact_person'     => 'nullable|string|max:100',
            'contact_phone'      => 'nullable|string|max:30',
            'start_date'         => ($partial ? 'sometimes|' : 'required|') . 'date',
            'end_date'           => 'nullable|date',
            'total_days'         => 'nullable|integer|min:0|max:365',
            'internship_type'    => 'nullable|string|max:50',
            'purpose'            => 'nullable|string|max:5000',
            'plan_content'       => 'nullable|string|max:10000',
            'daily_logs'         => 'nullable|string|max:50000',
            'company_evaluation' => 'nullable|string|max:10000',
            'attitude_score'     => 'nullable|integer|min:1|max:5',
            'skill_score'        => 'nullable|integer|min:1|max:5',
            'communication_score' => 'nullable|integer|min:1|max:5',
            'staff_evaluation'   => 'nullable|string|max:10000',
            'outcome'            => 'nullable|string|max:30',
        ];
        return $request->validate($rules);
    }

    private function validatePlacement(Request $request, bool $partial = false): array
    {
        $rules = [
            'student_id'                => ($partial ? 'sometimes|' : 'required|') . 'integer|exists:students,id',
            'company_name'              => ($partial ? 'sometimes|' : 'required|') . 'string|max:255',
            'job_title'                 => 'nullable|string|max:255',
            'start_date'                => ($partial ? 'sometimes|' : 'required|') . 'date',
            'end_date'                  => 'nullable|date',
            'employment_type'           => 'nullable|string|max:50',
            'monthly_salary'            => 'nullable|numeric|min:0|max:99999999',
            'weekly_hours'              => 'nullable|integer|min:0|max:168',
            'status'                    => 'nullable|string|max:30',
            'reasonable_accommodations' => 'nullable|string|max:5000',
            'next_followup_date'        => 'nullable|date',
            'separation_reason'         => 'nullable|string|max:5000',
        ];
        return $request->validate($rules);
    }

    private function authorizeClassroom(Request $request, int $classroomId): void
    {
        // ARCH-AUTH 統一: classroom_id 完全一致比較 (複数所属で誤拒否) を統一基盤へ。
        $this->authorizeClassroomId($request->user(), $classroomId, '他事業所のデータにはアクセスできません。');
    }
}
