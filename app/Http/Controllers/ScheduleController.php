<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    protected $calendarService;
    
    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }
    
    /**
     * Tampilkan form buat jadwal
     */
    public function create()
    {
        $hosts = User::where('role', 'host')->get();
        $assets = Asset::with('user')->get();
        
        return view('schedules.create', compact('hosts', 'assets'));
    }
    
    /**
     * Simpan jadwal baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'scheduled_at' => 'required|date|after:now',
        ]);
        
        // Ambil asset untuk mendapatkan user_id (host)
        $asset = Asset::findOrFail($validated['asset_id']);
        
        // Buat live session
        $liveSession = LiveSession::create([
            'asset_id' => $validated['asset_id'],
            'user_id' => $asset->user_id,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'scheduled'
        ]);
        
        // Buat event di Google Calendar
        $result = $this->calendarService->createEvent($liveSession);
        
        if ($result['success']) {
            // Simpan event ID ke database
            $liveSession->update([
                'google_calendar_event_id' => $result['event_id']
            ]);
            
            return redirect()->route('dashboard')
                ->with('success', 'Jadwal berhasil dibuat dan disinkronkan ke Google Calendar!');
        }
        
        return redirect()->back()
            ->with('error', 'Jadwal dibuat tapi gagal disinkronkan ke Calendar: ' . $result['message']);
    }
}