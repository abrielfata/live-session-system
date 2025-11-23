<?php

namespace App\Services;

use Revolution\Google\Sheets\Facades\Sheets;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditService
{
    /**
     * Tulis laporan audit ke Google Sheets
     */
    public function writeToSheet($liveSession)
    {
        try {
            $spreadsheetId = config('google-sheets.spreadsheet_id');
            
            // Data yang akan ditulis
            $row = [
                Carbon::now()->format('Y-m-d H:i:s'),
                $liveSession->id,
                $liveSession->user->name,
                $liveSession->asset->name,
                $liveSession->asset->platform,
                $liveSession->scheduled_at->format('Y-m-d H:i'),
                $liveSession->host_reported_gmv ?? 0,
                $liveSession->status,
            ];
            
            // Append data ke sheet
            Sheets::spreadsheet($spreadsheetId)
                ->sheet('Sheet1')
                ->append([$row]);
            
            Log::info('Audit written to Google Sheets', [
                'session_id' => $liveSession->id
            ]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Google Sheets Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buat header sheet (jalankan sekali saja)
     */
    public function createHeader()
    {
        try {
            $spreadsheetId = config('google-sheets.spreadsheet_id');
            
            $header = [
                'Timestamp',
                'Session ID',
                'Host',
                'Asset',
                'Platform',
                'Scheduled At',
                'GMV',
                'Status'
            ];
            
            Sheets::spreadsheet($spreadsheetId)
                ->sheet('Sheet1')
                ->append([$header]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Create Header Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}