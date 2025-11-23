<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\OcrSpaceService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramController extends Controller
{
    protected $ocrService;
    protected $auditService;
    protected $botToken;

    public function __construct(OcrSpaceService $ocrService, AuditService $auditService)
    {
        $this->ocrService = $ocrService;
        $this->auditService = $auditService;
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Handle incoming Telegram webhook
     */
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram Webhook', $update);

        // Cek apakah ada gambar
        if (isset($update['message']['photo'])) {
            $this->handlePhoto($update);
        } else {
            $this->sendMessage($update['message']['chat']['id'], 'Kirim screenshot GMV Anda!');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle photo/screenshot dari Telegram
     */
    protected function handlePhoto($update)
    {
        $chatId = $update['message']['chat']['id'];
        $photos = $update['message']['photo'];
        
        // Kirim pesan "sedang diproses"
        $this->sendMessage($chatId, "📸 Gambar diterima! Sedang memproses OCR...");
        
        // Ambil foto dengan resolusi tertinggi
        $photo = end($photos);
        $fileId = $photo['file_id'];

        try {
            Log::info('Processing photo', ['file_id' => $fileId, 'chat_id' => $chatId]);
            
            // Download foto dari Telegram
            $file = $this->getFile($fileId);
            $filePath = $file['file_path'];
            
            Log::info('File path obtained', ['path' => $filePath]);
            
            // Download gambar
            $imageUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
            $imageContent = file_get_contents($imageUrl);
            
            // Simpan ke storage
            $savedPath = 'screenshots/' . uniqid() . '.jpg';
            Storage::put($savedPath, $imageContent);
            
            Log::info('Image saved', ['path' => $savedPath]);
            
            // Proses OCR
            $this->sendMessage($chatId, "🔍 Mengekstrak data GMV...");
            
            $result = $this->ocrService->extractGmv($savedPath);
            
            Log::info('OCR Result', $result);
            
            if ($result['success']) {
                $gmv = $result['gmv'];
                
                // 🔥 SIMPAN KE DATABASE 🔥
                $saved = $this->saveToDatabase($chatId, $gmv, $savedPath);
                
                $message = "✅ *GMV Terdeteksi!*\n\n";
                $message .= "💰 GMV: Rp " . number_format($gmv, 0, ',', '.') . "\n";
                
                if ($saved) {
                    $message .= "✓ Data tersimpan ke database\n";
                    $message .= "✓ Session ID: " . $saved->id . "\n";
                } else {
                    $message .= "⚠️ Data GMV terdeteksi tapi belum tersimpan ke live session\n";
                    $message .= "(Buat jadwal live terlebih dahulu dari dashboard)\n";
                }
                
                $message .= "\n📝 Teks yang terdeteksi:\n";
                $message .= "```\n" . substr($result['raw_text'], 0, 200) . "...\n```";
                
                $this->sendMessage($chatId, $message, true);
            } else {
                $this->sendMessage($chatId, "❌ Gagal mendeteksi GMV\n\n" . 
                    "Alasan: " . $result['message'] . "\n\n" .
                    "Tips: Pastikan screenshot jelas dan ada angka GMV yang terlihat.");
            }
            
        } catch (\Exception $e) {
            Log::error('Telegram Photo Error: ' . $e->getMessage());
            $this->sendMessage($chatId, "❌ Terjadi kesalahan:\n" . $e->getMessage());
        }
    }

    /**
     * 🔥 FUNGSI BARU: Simpan GMV ke Database 🔥
     */
    protected function saveToDatabase($chatId, $gmv, $screenshotPath)
    {
        try {
            // Cari live session yang statusnya 'scheduled' untuk user ini
            // CATATAN: Kita pakai chat_id untuk identifikasi user
            // Untuk production, sebaiknya user Telegram melakukan /start dengan link unik
            
            // Untuk sementara, kita ambil session scheduled terbaru
            $liveSession = LiveSession::where('status', 'scheduled')
                ->orderBy('scheduled_at', 'desc')
                ->first();
            
            if (!$liveSession) {
                Log::warning('No scheduled session found for chat_id', ['chat_id' => $chatId]);
                return null;
            }
            
            // Update session dengan GMV dan screenshot
            $liveSession->update([
                'host_reported_gmv' => $gmv,
                'screenshot_path' => $screenshotPath,
                'status' => 'completed'
            ]);
            
            Log::info('GMV saved to database', [
                'session_id' => $liveSession->id,
                'gmv' => $gmv,
                'screenshot' => $screenshotPath
            ]);
            
            // Tulis ke Google Sheets (Audit)
            $this->auditService->writeToSheet($liveSession);
            
            return $liveSession;
            
        } catch (\Exception $e) {
            Log::error('Database save error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get file info dari Telegram
     */
    protected function getFile($fileId)
    {
        $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getFile", [
            'file_id' => $fileId
        ]);

        return $response->json()['result'];
    }

    /**
     * Kirim pesan ke Telegram
     */
    protected function sendMessage($chatId, $text, $markdown = false)
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        if ($markdown) {
            $params['parse_mode'] = 'Markdown';
        }
        
        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $params);
    }
}