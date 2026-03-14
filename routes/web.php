<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Models\Comment;
use App\Models\Rating;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return inertia('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'comments' => Comment::where('is_public', true)->latest()->take(10)->get(),
        'ratings' => Rating::where('is_public', true)->latest()->take(10)->get(),
    ]);
})->name('home');

// Guest + authenticated users can submit comments
Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
Route::post('ratings', [RatingController::class, 'store'])->name('ratings.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [StudentController::class, 'index'])->name('dashboard');

    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('students/{id}/restore', [StudentController::class, 'restore'])->name('students.restore');
    Route::delete('students/{id}/force-delete', [StudentController::class, 'forceDelete'])->name('students.force-delete');
    Route::post('students/{student}/qr/regenerate', [StudentController::class, 'regenerateQr'])->name('students.qr.regenerate');
    Route::get('students/{student}/attendance', [StudentController::class, 'attendance'])->name('students.attendance');

    Route::post('attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::get('comments', [CommentController::class, 'index'])->name('comments.index');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('ratings', [RatingController::class, 'index'])->name('ratings.index');
    Route::put('ratings/{rating}', [RatingController::class, 'update'])->name('ratings.update');
    Route::delete('ratings/{rating}', [RatingController::class, 'destroy'])->name('ratings.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('api/reports/stats', [ReportController::class, 'stats'])->name('api.reports.stats');
    Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
});

require __DIR__.'/settings.php';
