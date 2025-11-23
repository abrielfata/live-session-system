<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleWebhookController extends Controller
{
    /**
     * Handle webhook dari Google Calendar (LISTEN)
     * Menerima notifikasi jika ada perubahan/ralat
     */
    public function handle(Request $request)
    {
        Log::info('Google Calendar Webhook', $request->all());
        
        // Cek apakah ini notifikasi perubahan
        $resourceState = $request->header('X-Goog-Resource-State');
        
        if ($resourceState === 'update' || $resourceState === 'sync') {
            // Ambil event ID dari request
            $eventId = $request->input('id');
            
            if ($eventId) {
                // Cari live session berdasarkan event ID
                $liveSession = LiveSession::where('google_calendar_event_id', $eventId)->first();
                
                if ($liveSession) {
                    // Update status jadi cancelled (ralat)
                    $liveSession->update([
                        'status' => 'cancelled'
                    ]);
                    
                    Log::info('Live Session Cancelled', [
                        'session_id' => $liveSession->id,
                        'event_id' => $eventId
                    ]);
                }
            }
        }
        
        return response()->json(['success' => true]);
    }
}