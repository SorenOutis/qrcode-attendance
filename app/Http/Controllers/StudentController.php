<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        $date = CarbonImmutable::now()->toDateString();

        $students = Student::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'student_number',
                'email',
                'section',
                'qr_token',
                'schedule',
                'created_at',
            ]);

        $trashedStudents = Student::onlyTrashed()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'student_number',
                'email',
                'section',
                'qr_token',
                'schedule',
                'created_at',
                'deleted_at',
            ]);

        $latestByStudent = Attendance::query()
            ->whereDate('scanned_at', $date)
            ->orderByDesc('scanned_at')
            ->get(['id', 'student_id', 'status', 'scanned_at'])
            ->groupBy('student_id')
            ->map(fn ($items) => $items->first());

        $mapStudent = function ($student) use ($latestByStudent) {
            $latest = $latestByStudent->get($student->id);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
                'qr_token' => $student->qr_token,
                'schedule' => $student->schedule,
                'created_at' => $student->created_at,
                'deleted_at' => $student->deleted_at,
                'latest_attendance' => $latest
                    ? [
                        'id' => $latest->id,
                        'status' => $latest->status,
                        'scanned_at' => $latest->scanned_at,
                    ]
                    : null,
            ];
        };

        return Inertia::render('Dashboard', [
            'students'        => $students->map($mapStudent),
            'trashedStudents' => $trashedStudents->map($mapStudent),
        ]);
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return redirect()
            ->back()
            ->with('flash', [
                'student_deleted' => true,
            ]);
    }

    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $student->restore();

        return redirect()
            ->back()
            ->with('flash', [
                'student_restored' => true,
            ]);
    }

    public function forceDelete($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $student->forceDelete();

        return redirect()
            ->back()
            ->with('flash', [
                'student_permanently_deleted' => true,
            ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'student_number' => ['required', 'string', 'max:255', 'unique:students,student_number'],
            'email' => ['nullable', 'email', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.start' => ['required', 'date_format:H:i'],
            'schedule.*.end' => ['required', 'date_format:H:i'],
        ]);

        // Ensure each slot has start < end
        $data['schedule'] = collect($data['schedule'])
            ->filter(fn ($slot) => isset($slot['start'], $slot['end']))
            ->map(function ($slot) {
                return [
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];
            })
            ->values()
            ->all();

        $data['qr_token'] = Str::uuid()->toString();

        $student = Student::create($data);

        return redirect()
            ->back()
            ->with('flash', [
                'student_created' => true,
                'student_id' => $student->id,
            ]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'student_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'student_number')->ignore($student->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.start' => ['required', 'date_format:H:i'],
            'schedule.*.end' => ['required', 'date_format:H:i'],
        ]);

        $data['schedule'] = collect($data['schedule'])
            ->filter(fn ($slot) => isset($slot['start'], $slot['end']))
            ->map(function ($slot) {
                return [
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];
            })
            ->values()
            ->all();

        $student->update($data);

        return redirect()
            ->back()
            ->with('flash', [
                'student_updated' => true,
                'student_id' => $student->id,
            ]);
    }

    public function regenerateQr(Student $student)
    {
        $student->update([
            'qr_token' => Str::uuid()->toString(),
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'qr_regenerated' => true,
                'student_id' => $student->id,
            ]);
    }

    public function attendance(Student $student): \Illuminate\Http\JsonResponse
    {
        $history = $student->attendances()
            ->orderByDesc('scanned_at')
            ->get(['id', 'status', 'scanned_at'])
            ->map(fn ($a) => [
                'id'         => $a->id,
                'status'     => $a->status,
                'scanned_at' => $a->scanned_at->toISOString(),
            ]);

        return response()->json(['history' => $history]);
    }
}

