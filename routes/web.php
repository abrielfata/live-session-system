<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Services\OcrSpaceService;

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

// ========================================
// TESTING ROUTES - OCR
// ========================================

// Test 1: Cek API Key Valid atau Tidak
Route::get('/test-ocr-key', function() {
    $apiKey = env('OCRSPACE_API_KEY');
    
    if (empty($apiKey)) {
        return response()->json([
            'error' => 'API Key tidak ditemukan di .env',
            'hint' => 'Tambahkan OCRSPACE_API_KEY=xxx di file .env'
        ], 500);
    }
    
    // Test dengan gambar sample dari OCR.space
    $response = Http::timeout(60)->post('https://api.ocr.space/parse/image', [
        'apikey' => $apiKey,
        'url' => 'https://api.ocr.space/Content/Images/receipt-ocr-original.jpg',
        'language' => 'eng',
        'isOverlayRequired' => false
    ]);
    
    $data = $response->json();
    
    // Format response yang mudah dibaca
    $result = [
        'test_info' => [
            'api_key_preview' => substr($apiKey, 0, 8) . '***',
            'test_image' => 'Receipt sample dari OCR.space',
            'timestamp' => now()->toDateTimeString()
        ],
        'api_response' => [
            'http_status' => $response->status(),
            'is_errored' => $data['IsErroredOnProcessing'] ?? false,
            'error_message' => $data['ErrorMessage'] ?? null,
            'ocr_exit_code' => $data['OCRExitCode'] ?? null,
            'processing_time_ms' => $data['ProcessingTimeInMilliseconds'] ?? null
        ],
        'result' => [
            'has_text' => !empty($data['ParsedResults'][0]['ParsedText'] ?? ''),
            'text_preview' => isset($data['ParsedResults'][0]['ParsedText']) 
                ? substr($data['ParsedResults'][0]['ParsedText'], 0, 300) 
                : 'NO TEXT DETECTED'
        ]
    ];
    
    // Tambah diagnosis
    if ($data['IsErroredOnProcessing'] ?? false) {
        $result['diagnosis'] = '❌ API Key TIDAK VALID atau LIMIT HABIS';
        $result['solution'] = 'Daftar API key baru di: https://ocr.space/ocrapi/freekey';
    } elseif (!empty($data['ParsedResults'][0]['ParsedText'] ?? '')) {
        $result['diagnosis'] = '✅ API Key VALID dan OCR berfungsi!';
        $result['solution'] = 'API key OK. Lanjut test dengan gambar TikTok.';
    } else {
        $result['diagnosis'] = '⚠️ API Key valid tapi OCR tidak detect text';
        $result['solution'] = 'Coba kirim gambar yang lebih jelas';
    }
    
    // Tampilkan full response untuk debugging (optional)
    $result['full_api_response'] = $data;
    
    return response()->json($result, JSON_PRETTY_PRINT);
})->name('test.ocr.key');

// Test 2: Upload Gambar Sendiri untuk Test OCR
Route::get('/test-ocr-upload', function() {
    return view('test-ocr-upload');
})->name('test.ocr.upload');

Route::post('/test-ocr-upload', function(Request $request) {
    $request->validate([
        'image' => 'required|image|max:5120' // Max 5MB
    ]);
    
    try {
        // Simpan gambar
        $path = $request->file('image')->store('test-uploads', 'local');
        
        // Jalankan OCR
        $ocrService = app(OcrSpaceService::class);
        $result = $ocrService->extractGmv($path);
        
        // Format response
        return response()->json([
            'upload_info' => [
                'filename' => $request->file('image')->getClientOriginalName(),
                'size_bytes' => $request->file('image')->getSize(),
                'saved_path' => $path,
                'timestamp' => now()->toDateTimeString()
            ],
            'ocr_result' => $result,
            'diagnosis' => $result['success'] 
                ? "✅ OCR Berhasil! GMV terdeteksi: Rp " . number_format($result['gmv'], 0, ',', '.')
                : "❌ OCR Gagal: " . $result['message']
        ], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
})->name('test.ocr.upload.post');

// Test 3: Test OCR dengan URL gambar
Route::get('/test-ocr-url', function(Request $request) {
    $imageUrl = $request->query('url');
    
    if (empty($imageUrl)) {
        return response()->json([
            'error' => 'Parameter "url" diperlukan',
            'example' => '/test-ocr-url?url=https://example.com/image.jpg'
        ], 400);
    }
    
    $apiKey = env('OCRSPACE_API_KEY');
    
    $response = Http::timeout(60)->post('https://api.ocr.space/parse/image', [
        'apikey' => $apiKey,
        'url' => $imageUrl,
        'language' => 'eng'
    ]);
    
    $data = $response->json();
    
    return response()->json([
        'test_info' => [
            'image_url' => $imageUrl,
            'api_key_preview' => substr($apiKey, 0, 8) . '***'
        ],
        'is_errored' => $data['IsErroredOnProcessing'] ?? false,
        'error_message' => $data['ErrorMessage'] ?? null,
        'text_detected' => $data['ParsedResults'][0]['ParsedText'] ?? 'NO TEXT',
        'full_response' => $data
    ], JSON_PRETTY_PRINT);
})->name('test.ocr.url');

require __DIR__.'/auth.php';