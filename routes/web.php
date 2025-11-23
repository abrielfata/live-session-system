<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard berdasarkan role
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'manager') {
            return redirect()->route('dashboard.manager');
        }
        return redirect()->route('dashboard.host');
    })->name('dashboard');
    
    // Dashboard Manager
    Route::get('/dashboard/manager', [DashboardController::class, 'manager'])
        ->middleware('role:manager')
        ->name('dashboard.manager');
    
    // Dashboard Host
    Route::get('/dashboard/host', [DashboardController::class, 'host'])
        ->middleware('role:host')
        ->name('dashboard.host');
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Route untuk Manager
Route::middleware(['auth', 'role:manager'])->group(function () {
    Route::get('/schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
});

Route::get('/test-ocr-upload', function() {
    return view('test-ocr-upload');
})->name('test.ocr');

Route::get('/test-ocr-key', function() {
    $apiKey = env('OCRSPACE_API_KEY');
    
    // Test dengan gambar sample dari OCR.space
    $response = Http::timeout(60)->post('https://api.ocr.space/parse/image', [
        'apikey' => $apiKey,
        'url' => 'https://api.ocr.space/Content/Images/receipt-ocr-original.jpg',
        'language' => 'eng'
    ]);
    
    $data = $response->json();
    
    return response()->json([
        'api_key_first_5_chars' => substr($apiKey, 0, 5) . '...',
        'status_code' => $response->status(),
        'is_errored' => $data['IsErroredOnProcessing'] ?? false,
        'error_message' => $data['ErrorMessage'] ?? null,
        'has_results' => isset($data['ParsedResults']) && !empty($data['ParsedResults']),
        'parsed_text_preview' => isset($data['ParsedResults'][0]['ParsedText']) 
            ? substr($data['ParsedResults'][0]['ParsedText'], 0, 200) 
            : 'NO TEXT',
        'full_response' => $data
    ], JSON_PRETTY_PRINT);
});

require __DIR__.'/auth.php';