<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
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

        return Inertia::render('Dashboard', [
            'students' => $students,
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
}

