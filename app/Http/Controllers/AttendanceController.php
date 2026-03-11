<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
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
        $date = $now->toDateString();
        $time = $now->format('H:i');

        $schedule = collect($student->schedule ?? []);

        // Find the active slot based on current time
        $slotIndex = $schedule->search(function ($slot) use ($time) {
            if (! isset($slot['start'], $slot['end'])) {
                return false;
            }

            return $time >= $slot['start'] && $time <= $slot['end'];
        });

        if ($slotIndex === false) {
            return response()->json([
                'message' => 'No active schedule for this time.',
            ], 422);
        }

        $slot = $schedule[$slotIndex];

        $start = CarbonImmutable::parse($date.' '.$slot['start']);
        $end = CarbonImmutable::parse($date.' '.$slot['end']);

        $graceMinutes = 15;

        // Check if there is already an attendance for this slot today
        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('scanned_at', $date)
            ->where('slot_index', $slotIndex)
            ->orderBy('scanned_at')
            ->get();

        if ($existing->isEmpty()) {
            if ($now->lessThanOrEqualTo($start->addMinutes($graceMinutes))) {
                $status = 'Present';
            } elseif ($now->lessThanOrEqualTo($end)) {
                $status = 'Late';
            } else {
                $status = 'Time Out';
            }
        } else {
            // Any subsequent scan for the same slot is treated as time out
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

