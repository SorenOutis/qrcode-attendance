<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Present,Late,Time Out,Absent'],
        ]);

        $attendance->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back();
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $student = Student::where('qr_token', $validated['token'])->first();

        if (! $student) {
            return response()->json([
                'message' => 'Invalid or expired QR code.',
            ], 404);
        }

        $now = CarbonImmutable::now();
        $dayOfWeek = $now->format('l'); // Monday, Tuesday, etc.
        $date = $now->toDateString();
        $time = $now->format('H:i');

        $schedule = collect($student->schedule ?? [])
            ->filter(fn ($slot) => isset($slot['day'], $slot['start'], $slot['end']))
            ->filter(fn ($slot) => $slot['day'] === $dayOfWeek)
            ->values();

        if ($schedule->isEmpty()) {
            return response()->json([
                'message' => "No schedule configured for {$student->name} today ($dayOfWeek).",
            ], 422);
        }

        // Map ANY scan time to a slot:
        // - If within a slot: use that slot
        // - If between slots / before first slot: use the next upcoming slot
        // - If after last slot: use the last slot
        $slotIndex = null;
        $slot = null;
        $start = null;
        $end = null;

        foreach ($schedule as $i => $candidate) {
            $candidateStart = CarbonImmutable::parse($date.' '.$candidate['start']);
            $candidateEnd = CarbonImmutable::parse($date.' '.$candidate['end']);

            if ($now->betweenIncluded($candidateStart, $candidateEnd)) {
                $slotIndex = (int) $i;
                $slot = $candidate;
                $start = $candidateStart;
                $end = $candidateEnd;
                break;
            }

            if ($now->lessThan($candidateStart)) {
                // Next upcoming slot
                $slotIndex = (int) $i;
                $slot = $candidate;
                $start = $candidateStart;
                $end = $candidateEnd;
                break;
            }
        }

        if ($slotIndex === null) {
            // After all slots → last slot
            $slotIndex = (int) ($schedule->count() - 1);
            $slot = $schedule[$slotIndex];
            $start = CarbonImmutable::parse($date.' '.$slot['start']);
            $end = CarbonImmutable::parse($date.' '.$slot['end']);
        }

        $graceMinutes = 15;

        // Count existing attendance for this student today
        $dailyRecords = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get();

        if ($dailyRecords->count() >= 2) {
            return response()->json([
                'message' => 'You have already completed your attendance for today. You cannot be scanned again until the next day.',
            ], 422);
        }

        if ($dailyRecords->isEmpty()) {
            // First scan of the day
            if ($now->lessThanOrEqualTo($start->addMinutes($graceMinutes))) {
                $status = 'Present';
            } else {
                $status = 'Late';
            }
        } else {
            // Second scan of the day
            $status = 'Time Out';
        }

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'scanned_at' => $now,
            'status' => $status,
            'slot_index' => $slotIndex,
            'slot_start' => $slot['start'],
            'slot_end' => $slot['end'],
        ]);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
            ],
            'attendance' => [
                'id' => $attendance->id,
                'scanned_at' => $attendance->scanned_at,
                'status' => $attendance->status,
                'slot_start' => $attendance->slot_start,
                'slot_end' => $attendance->slot_end,
            ],
        ]);
    }
}
