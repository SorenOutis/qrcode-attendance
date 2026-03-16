<?php

use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;

test('scans at or after 15 minutes of schedule start are marked as Late', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $student = Student::create([
        'name' => 'Test Student',
        'student_number' => '2021-0001',
        'email' => 'test@example.com',
        'section' => 'BSIT-1A',
        'qr_token' => 'test-token',
        'schedule' => [
            [
                'day' => 'Monday',
                'start' => '13:00',
                'end' => '14:00',
            ],
        ],
    ]);

    // Monday, March 16, 2026.
    $baseDate = '2026-03-16';

    // 1:14 PM -> Present
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:14:59"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Present');

    // Reset for next scan
    $student->attendances()->delete();

    // 1:15 PM -> Late (as per user request: "1:15 is considered late")
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:15:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Late');

    // Reset for next scan
    $student->attendances()->delete();

    // 1:19 PM -> Late
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:19:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Late');
});
