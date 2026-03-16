<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports');
    }

    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // 1. Attendance Rate over time
        $dailystats = Attendance::where('scanned_at', '>=', $startDate)
            ->selectRaw('DATE(scanned_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 2. Section Comparison
        $sectionStats = Student::withCount(['attendances' => function ($query) use ($startDate) {
            $query->where('scanned_at', '>=', $startDate);
        }])
            ->get()
            ->groupBy('section')
            ->map(fn ($students) => $students->sum('attendances_count'));

        // 3. Status distribution
        $statusStats = Attendance::where('scanned_at', '>=', $startDate)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $dailystats,
            'sections' => $sectionStats,
            'status' => $statusStats,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        return new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Student Name', 'ID Number', 'Section', 'Status', 'Time']);

            Attendance::with('student')
                ->orderByDesc('scanned_at')
                ->chunk(100, function ($attendances) use ($handle) {
                    foreach ($attendances as $attendance) {
                        fputcsv($handle, [
                            $attendance->scanned_at->toDateString(),
                            $attendance->student->name,
                            $attendance->student->student_number,
                            $attendance->student->section,
                            $attendance->status,
                            $attendance->scanned_at->toTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}
