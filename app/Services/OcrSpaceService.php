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
            Log::info('OCR: Starting extraction', ['path' => $imagePath]);

            // Baca file dengan Storage facade
            if (!Storage::exists($imagePath)) {
                Log::error('OCR: File not found', ['path' => $imagePath]);
                return ['success' => false, 'message' => 'File tidak ditemukan'];
            }

            $fileContents = Storage::get($imagePath);
            $base64 = base64_encode($fileContents);

            Log::info('OCR: File encoded', [
                'size_bytes' => strlen($fileContents),
                'base64_length' => strlen($base64)
            ]);

            // Request ke OCR.space dengan base64 (lebih reliable)
            $response = Http::timeout(60)->asMultipart()->post($this->apiUrl, [
                [
                    'name' => 'apikey',
                    'contents' => $this->apiKey
                ],
                [
                    'name' => 'base64Image',
                    'contents' => 'data:image/jpeg;base64,' . $base64
                ],
                [
                    'name' => 'language',
                    'contents' => 'eng'
                ],
                [
                    'name' => 'isOverlayRequired',
                    'contents' => 'false'
                ],
                [
                    'name' => 'OCREngine',
                    'contents' => '2' // Engine 2 lebih baik untuk angka
                ]
            ]);

            $data = $response->json();

            // LOG DETAIL RESPONSE
            Log::info('OCR API Response', [
                'status' => $response->status(),
                'is_errored' => $data['IsErroredOnProcessing'] ?? 'unknown',
                'error_message' => $data['ErrorMessage'] ?? null,
                'ocr_exit_code' => $data['OCRExitCode'] ?? null,
                'processing_time_ms' => $data['ProcessingTimeInMilliseconds'] ?? null
            ]);

            // CEK ERROR DARI OCR.SPACE
            if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing']) {
                $errorMessage = $data['ErrorMessage'] ?? 'Unknown OCR error';
                
                // ErrorMessage bisa array atau string
                if (is_array($errorMessage)) {
                    $errorMessage = implode(', ', $errorMessage);
                }
                
                Log::error('OCR Processing Error', [
                    'error' => $errorMessage,
                    'details' => $data['ErrorDetails'] ?? null
                ]);
                
                return [
                    'success' => false,
                    'message' => 'OCR Error: ' . $errorMessage
                ];
            }

            // CEK HASIL PARSING
            if (!isset($data['ParsedResults']) || empty($data['ParsedResults'])) {
                Log::error('OCR: No ParsedResults', ['full_response' => $data]);
                return [
                    'success' => false,
                    'message' => 'OCR tidak mengembalikan hasil'
                ];
            }

            // Ambil text hasil OCR
            $rawText = $data['ParsedResults'][0]['ParsedText'] ?? '';

            Log::info('OCR Raw Text', ['text' => substr($rawText, 0, 500)]);

            if (empty(trim($rawText))) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada text terdeteksi. Coba kirim gambar lebih jelas.',
                    'raw_text' => ''
                ];
            }

            // EKSTRAK GMV DENGAN PRIORITAS PATTERN
            $gmv = null;
            $allCandidates = [];

            // PRIORITAS 1: Cari "Pendapatan" + angka dengan K/M (TikTok Shop)
            // Contoh: "Pendapatan 286.9K" atau "Pendapatan\n286.9K"
            if (preg_match('/Pendapatan[^\d]*(\d+(?:[.,]\d+)?)\s*([KkMmJjtT])/i', $rawText, $match)) {
                $cleanNum = $this->cleanDecimalNumber($match[1]);
                $suffix = strtoupper($match[2]);
                
                if ($suffix === 'K') {
                    $cleanNum *= 1000;
                } elseif (in_array($suffix, ['M', 'JT'])) {
                    $cleanNum *= 1000000;
                } elseif ($suffix === 'T') {
                    $cleanNum *= 1000000000;
                }
                
                $gmv = $cleanNum;
                
                Log::info('OCR: Found Pendapatan pattern (PRIORITY 1)', [
                    'original' => $match[0],
                    'gmv' => $gmv
                ]);
            }

            // PRIORITAS 2: Jika tidak ada "Pendapatan", cari "GMV" atau "Total Penjualan"
            if (!$gmv && preg_match('/(GMV|Total\s+Penjualan|Sales)[^\d]*(Rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})*)/i', $rawText, $match)) {
                $gmv = $this->cleanWholeNumber($match[3]);
                
                Log::info('OCR: Found GMV/Sales pattern (PRIORITY 2)', [
                    'original' => $match[0],
                    'gmv' => $gmv
                ]);
            }

            // PRIORITAS 3: Cari semua angka dengan suffix K/M/Jt sebagai fallback
            if (!$gmv) {
                preg_match_all('/(\d+(?:[.,]\d+)?)\s*([KkMmJjtT])/i', $rawText, $suffixMatches);
                if (!empty($suffixMatches[1])) {
                    foreach ($suffixMatches[1] as $index => $num) {
                        $cleanNum = $this->cleanDecimalNumber($num);
                        $suffix = strtoupper($suffixMatches[2][$index]);
                        
                        if ($suffix === 'K') {
                            $cleanNum *= 1000;
                        } elseif (in_array($suffix, ['M', 'JT'])) {
                            $cleanNum *= 1000000;
                        } elseif ($suffix === 'T') {
                            $cleanNum *= 1000000000;
                        }
                        
                        $allCandidates[] = $cleanNum;
                    }
                    
                    if (!empty($allCandidates)) {
                        // Ambil terbesar dari kandidat K/M/Jt
                        $gmv = max($allCandidates);
                        
                        Log::info('OCR: Found suffix pattern (PRIORITY 3)', [
                            'gmv' => $gmv,
                            'all_candidates' => $allCandidates
                        ]);
                    }
                }
            }

            // PRIORITAS 4: Cari "Rp" + angka sebagai fallback terakhir
            if (!$gmv) {
                if (preg_match('/Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*)/i', $rawText, $match)) {
                    $gmv = $this->cleanWholeNumber($match[1]);
                    
                    Log::info('OCR: Found Rp pattern (PRIORITY 4)', [
                        'original' => $match[0],
                        'gmv' => $gmv
                    ]);
                }
            }

            // Jika GMV terdeteksi
            if ($gmv && $gmv >= 1000) { // Minimal Rp 1.000
                Log::info('OCR: GMV Detected', [
                    'gmv' => $gmv,
                    'method' => 'priority_pattern'
                ]);
                
                return [
                    'success' => true,
                    'gmv' => $gmv,
                    'raw_text' => $rawText
                ];
            }

            return [
                'success' => false,
                'message' => 'Tidak ada angka GMV terdeteksi dalam gambar',
                'raw_text' => $rawText
            ];

        } catch (\Exception $e) {
            Log::error('OCR Exception', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clean decimal number (untuk format: 286.9 atau 1,5)
     * Output: float dengan desimal
     */
    private function cleanDecimalNumber($numberString)
    {
        // Ganti koma dengan titik untuk desimal
        $clean = str_replace(',', '.', $numberString);
        return (float) $clean;
    }

    /**
     * Clean whole number (untuk format: 1.500.000 atau 1,500,000)
     * Output: integer tanpa desimal
     */
    private function cleanWholeNumber($numberString)
    {
        // Hapus semua titik dan koma (separator ribuan)
        $clean = str_replace(['.', ','], '', $numberString);
        return (float) $clean;
    }
}