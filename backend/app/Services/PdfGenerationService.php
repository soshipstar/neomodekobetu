<?php

namespace App\Services;

use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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

        $pdf = Pdf::loadView('pdf.support-plan', [
            'plan' => $plan,
            'student' => $plan->student,
            'classroom' => $plan->classroom,
            'details' => $plan->details->sortBy('sort_order'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'ipag');

        return $this->storePdf($pdf, 'support-plans', "plan_{$plan->id}_{$plan->student_id}");
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

        $pdf = Pdf::loadView('pdf.monitoring', [
            'record' => $record,
            'plan' => $record->plan,
            'student' => $record->student,
            'classroom' => $record->classroom,
            'details' => $record->details,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'ipag');

        return $this->storePdf($pdf, 'monitoring', "monitoring_{$record->id}_{$record->student_id}");
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

        $pdf = Pdf::loadView('pdf.kakehashi', [
            'period' => $period,
            'student' => $period->student,
            'classroom' => $period->student->classroom,
            'staffEntries' => $period->staffEntries,
            'guardianEntries' => $period->guardianEntries,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'ipag');

        return $this->storePdf($pdf, 'kakehashi', "kakehashi_{$period->id}_{$period->student_id}");
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

        $pdf = Pdf::loadView('pdf.newsletter', [
            'newsletter' => $newsletter,
            'classroom' => $newsletter->classroom,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'ipag');

        return $this->storePdf(
            $pdf,
            'newsletters',
            "newsletter_{$newsletter->id}_{$newsletter->year}_{$newsletter->month}"
        );
    }

    /**
     * Store the generated PDF to the configured disk and return its path.
     */
    private function storePdf(object $pdf, string $directory, string $basename): string
    {
        $filename = "{$basename}_" . now()->format('YmdHis') . '.pdf';
        $path = "pdf/{$directory}/{$filename}";

        Storage::disk('s3')->put($path, $pdf->output());

        return $path;
    }
}
