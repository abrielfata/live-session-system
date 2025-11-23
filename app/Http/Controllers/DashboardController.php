<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard Manager: Lihat semua jadwal
     */
    public function manager()
    {
        $sessions = LiveSession::with(['user', 'asset'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(10);
        
        $totalScheduled = LiveSession::where('status', 'scheduled')->count();
        $totalCompleted = LiveSession::where('status', 'completed')->count();
        $totalCancelled = LiveSession::where('status', 'cancelled')->count();
        
        return view('dashboard.manager', compact(
            'sessions',
            'totalScheduled',
            'totalCompleted',
            'totalCancelled'
        ));
    }
    
    /**
     * Dashboard Host: Lihat jadwal sendiri
     */
    public function host()
    {
        $sessions = LiveSession::with('asset')
            ->where('user_id', auth()->id())
            ->orderBy('scheduled_at', 'desc')
            ->paginate(10);
        
        return view('dashboard.host', compact('sessions'));
    }
}