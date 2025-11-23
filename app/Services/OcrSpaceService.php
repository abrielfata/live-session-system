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

    /**
     * Ekstrak GMV dari gambar menggunakan OCR.space
     */
    public function extractGmv(string $imagePath)
    {
        try {
            // Baca file dengan cara yang benar untuk Windows
            $fileContent = null;
            
            // Jika path adalah relative path (tanpa C:\)
            if (!file_exists($imagePath)) {
                // Coba dengan Storage facade
                if (Storage::exists($imagePath)) {
                    $fileContent = Storage::get($imagePath);
                    Log::info('File read via Storage', ['path' => $imagePath]);
                } else {
                    throw new \Exception("File tidak ditemukan: {$imagePath}");
                }
            } else {
                // Path absolut
                $fileContent = file_get_contents($imagePath);
                Log::info('File read via file_get_contents', ['path' => $imagePath]);
            }
            
            if (!$fileContent) {
                throw new \Exception("Gagal membaca file");
            }

            // Kirim gambar ke OCR.space API
            $response = Http::attach(
                'file', 
                $fileContent, 
                basename($imagePath)
            )->post($this->apiUrl, [
                'apikey' => $this->apiKey,
                'language' => 'eng',
                'isOverlayRequired' => false,
                'scale' => true,
                'OCREngine' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Ambil text hasil OCR
                $ocrText = $data['ParsedResults'][0]['ParsedText'] ?? '';
                
                Log::info('OCR Raw Text', ['text' => $ocrText]);
                
                // PATTERN 1: Cari "Rp" diikuti angka
                preg_match_all('/Rp\s*(\d+(?:[.,]\d+)*)/i', $ocrText, $rpMatches);
                
                // PATTERN 2: Cari "GMV" diikuti angka
                preg_match_all('/GMV[^0-9]*(\d+(?:[.,]\d+)*)/i', $ocrText, $gmvMatches);
                
                // PATTERN 3: Cari semua angka dengan format K (ribu)
                preg_match_all('/(\d+(?:[.,]\d+)?)\s*[Kk]/i', $ocrText, $kMatches);
                
                // PATTERN 4: Cari semua angka besar
                preg_match_all('/\d{1,3}(?:[.,]\d{3})+/', $ocrText, $bigNumbers);
                
                $candidates = [];
                
                // Kumpulkan kandidat dari Rp pattern
                if (!empty($rpMatches[1])) {
                    foreach ($rpMatches[1] as $match) {
                        $clean = $this->cleanNumber($match);
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found Rp', ['value' => $clean]);
                        }
                    }
                }
                
                // Kumpulkan kandidat dari GMV pattern
                if (!empty($gmvMatches[1])) {
                    foreach ($gmvMatches[1] as $match) {
                        $clean = $this->cleanNumber($match);
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found GMV', ['value' => $clean]);
                        }
                    }
                }
                
                // Kumpulkan kandidat dari format K (4,6K = 4600)
                if (!empty($kMatches[1])) {
                    foreach ($kMatches[1] as $match) {
                        $clean = $this->cleanNumber($match) * 1000;
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found K format', ['value' => $clean]);
                        }
                    }
                }
                
                // Kumpulkan angka besar
                if (!empty($bigNumbers[0])) {
                    foreach ($bigNumbers[0] as $match) {
                        $clean = $this->cleanNumber($match);
                        if ($clean > 0) {
                            $candidates[] = $clean;
                            Log::info('Found big number', ['value' => $clean]);
                        }
                    }
                }
                
                if (!empty($candidates)) {
                    // Ambil angka terbesar
                    $gmv = max($candidates);
                    
                    Log::info('OCR Success', [
                        'gmv' => $gmv,
                        'candidates' => $candidates
                    ]);
                    
                    return [
                        'success' => true,
                        'gmv' => $gmv,
                        'raw_text' => $ocrText,
                        'all_numbers' => $candidates
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Tidak ada angka terdeteksi',
                    'raw_text' => $ocrText
                ];
            }
            
            return [
                'success' => false,
                'message' => 'OCR API gagal: ' . $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('OCR Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Bersihkan format angka
     */
    private function cleanNumber($numberString)
    {
        // Hapus semua kecuali angka, koma, titik
        $clean = preg_replace('/[^\d,.]/', '', $numberString);
        
        // Format Indonesia: 1.000.000 atau 1,000,000
        // Ganti separator ribuan dengan kosong
        $clean = str_replace(['.', ','], '', $clean);
        
        return (float) $clean;
    }
}