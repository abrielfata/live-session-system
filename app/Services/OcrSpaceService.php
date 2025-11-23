<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrSpaceService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.ocr.space/parse/image';

    public function __construct()
    {
        $this->apiKey = config('services.ocrspace.api_key');
    }

    public function extractGmv(string $imagePath)
    {
        try {
            // Baca file
            $fileContent = null;
            
            if (!file_exists($imagePath)) {
                if (Storage::exists($imagePath)) {
                    $fileContent = Storage::get($imagePath);
                    Log::info('File read via Storage', ['path' => $imagePath]);
                } else {
                    throw new \Exception("File tidak ditemukan: {$imagePath}");
                }
            } else {
                $fileContent = file_get_contents($imagePath);
                Log::info('File read via file_get_contents', ['path' => $imagePath]);
            }
            
            if (!$fileContent) {
                throw new \Exception("Gagal membaca file");
            }

            Log::info('Sending to OCR API', [
                'file_size' => strlen($fileContent),
                'api_key_preview' => substr($this->apiKey, 0, 5) . '***'
            ]);

            // PERBAIKAN: Hapus parameter yang invalid, gunakan format yang benar
            $response = Http::timeout(60)->attach(
                'file', 
                $fileContent, 
                basename($imagePath)
            )->post($this->apiUrl, [
                'apikey' => $this->apiKey,
                'language' => 'eng',
                'OCREngine' => '2',  // String, bukan integer
            ]);

            Log::info('OCR API Response Status', ['status' => $response->status()]);
            Log::info('OCR API Full Response', ['response' => $response->json()]);

            if ($response->successful()) {
                $data = $response->json();
                
                // CEK ERROR
                if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing']) {
                    // PERBAIKAN: ErrorMessage bisa array atau string
                    $errorMessage = $data['ErrorMessage'] ?? 'Unknown error';
                    if (is_array($errorMessage)) {
                        $errorMessage = implode(', ', $errorMessage);
                    }
                    
                    $errorDetails = $data['ErrorDetails'] ?? '';
                    
                    Log::error('OCR Processing Error', [
                        'error_message' => $errorMessage,
                        'error_details' => $errorDetails
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => "OCR Error: {$errorMessage}",
                    ];
                }
                
                // CEK hasil
                if (!isset($data['ParsedResults']) || empty($data['ParsedResults'])) {
                    Log::error('No ParsedResults', ['full_response' => $data]);
                    
                    return [
                        'success' => false,
                        'message' => 'OCR tidak mengembalikan hasil',
                    ];
                }
                
                $ocrText = $data['ParsedResults'][0]['ParsedText'] ?? '';
                
                Log::info('OCR Raw Text', ['text' => $ocrText]);
                
                if (empty(trim($ocrText))) {
                    return [
                        'success' => false,
                        'message' => 'OCR tidak menemukan text dalam gambar',
                        'raw_text' => $ocrText
                    ];
                }
                
                // PATTERN MATCHING untuk ekstrak GMV
                $candidates = [];
                
                // Pattern 1: Rp + angka
                preg_match_all('/Rp\s*(\d+(?:[.,]\d+)*)/i', $ocrText, $rpMatches);
                if (!empty($rpMatches[1])) {
                    foreach ($rpMatches[1] as $match) {
                        $clean = $this->cleanNumber($match);
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found Rp pattern', ['value' => $clean]);
                        }
                    }
                }
                
                // Pattern 2: Angka + K (contoh: 4,6K = 4600)
                preg_match_all('/(\d+(?:[.,]\d+)?)\s*[Kk]/i', $ocrText, $kMatches);
                if (!empty($kMatches[1])) {
                    foreach ($kMatches[1] as $match) {
                        $clean = $this->cleanNumber($match) * 1000;
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found K pattern', ['value' => $clean]);
                        }
                    }
                }
                
                // Pattern 3: Semua angka >= 100
                preg_match_all('/\d+(?:[.,]\d+)*/', $ocrText, $allNumbers);
                if (!empty($allNumbers[0])) {
                    foreach ($allNumbers[0] as $match) {
                        $clean = $this->cleanNumber($match);
                        if ($clean >= 100) {
                            $candidates[] = $clean;
                        }
                    }
                }
                
                if (!empty($candidates)) {
                    // Ambil angka terbesar
                    $gmv = max($candidates);
                    
                    Log::info('OCR Success', [
                        'gmv' => $gmv,
                        'all_candidates' => $candidates
                    ]);
                    
                    return [
                        'success' => true,
                        'gmv' => $gmv,
                        'raw_text' => $ocrText,
                        'all_numbers' => array_unique($candidates)
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Tidak ada angka >= 100 terdeteksi',
                    'raw_text' => $ocrText
                ];
            }
            
            Log::error('OCR API HTTP Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('OCR Exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Bersihkan format angka (1.000.000 atau 1,234,567 → 1000000)
     */
    private function cleanNumber($numberString)
    {
        // Hapus semua kecuali angka
        $clean = preg_replace('/[^\d]/', '', $numberString);
        return (float) $clean;
    }
}