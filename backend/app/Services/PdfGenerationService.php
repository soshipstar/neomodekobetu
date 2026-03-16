<?php

namespace App\Services;

use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class PdfGenerationService
{
    /**
     * Generate a PDF for an individual support plan.
     *
     * @param  IndividualSupportPlan  $plan
     * @return string  Storage path of the generated PDF
     */
    public function generateSupportPlanPdf(IndividualSupportPlan $plan): string
    {
        $plan->load(['student', 'classroom', 'details', 'creator']);

        $html = View::make('pdf.support-plan', [
            'plan' => $plan,
            'student' => $plan->student,
            'classroom' => $plan->classroom,
            'details' => $plan->details->sortBy('sort_order'),
        ])->render();

        return $this->storePdf($html, 'support-plans', "plan_{$plan->id}_{$plan->student_id}");
    }

    /**
     * Generate a PDF for a monitoring record.
     *
     * @param  MonitoringRecord  $record
     * @return string  Storage path of the generated PDF
     */
    public function generateMonitoringPdf(MonitoringRecord $record): string
    {
        $record->load(['plan.details', 'student', 'classroom', 'details', 'creator']);

        $html = View::make('pdf.monitoring', [
            'record' => $record,
            'plan' => $record->plan,
            'student' => $record->student,
            'classroom' => $record->classroom,
            'details' => $record->details,
        ])->render();

        return $this->storePdf($html, 'monitoring', "monitoring_{$record->id}_{$record->student_id}");
    }

    /**
     * Generate a PDF for a Kakehashi period report.
     *
     * @param  KakehashiPeriod  $period
     * @return string  Storage path of the generated PDF
     */
    public function generateKakehashiPdf(KakehashiPeriod $period): string
    {
        $period->load(['student.classroom', 'staffEntries', 'guardianEntries']);

        $html = View::make('pdf.kakehashi', [
            'period' => $period,
            'student' => $period->student,
            'classroom' => $period->student->classroom,
            'staffEntries' => $period->staffEntries,
            'guardianEntries' => $period->guardianEntries,
        ])->render();

        return $this->storePdf($html, 'kakehashi', "kakehashi_{$period->id}_{$period->student_id}");
    }

    /**
     * Generate a PDF for a newsletter.
     *
     * @param  Newsletter  $newsletter
     * @return string  Storage path of the generated PDF
     */
    public function generateNewsletterPdf(Newsletter $newsletter): string
    {
        $newsletter->load(['classroom']);

        $html = View::make('pdf.newsletter', [
            'newsletter' => $newsletter,
            'classroom' => $newsletter->classroom,
        ])->render();

        return $this->storePdf(
            $html,
            'newsletters',
            "newsletter_{$newsletter->id}_{$newsletter->year}_{$newsletter->month}"
        );
    }

    /**
     * Store the generated PDF to the configured disk and return its path.
     */
    private function storePdf(string $html, string $directory, string $basename): string
    {
        $filename = "{$basename}_" . now()->format('YmdHis') . '.pdf';
        $path = "pdf/{$directory}/{$filename}";

        $pdfBinary = PuppeteerPdfService::htmlToPdf($html);
        Storage::disk('s3')->put($path, $pdfBinary);

        return $path;
    }
}
