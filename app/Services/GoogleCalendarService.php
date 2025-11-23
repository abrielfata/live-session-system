<?php

namespace App\Services;

use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    /**
     * Buat event baru di Google Calendar (PUSH)
     */
    public function createEvent($liveSession)
    {
        try {
            $event = new Event;
            
            $startTime = Carbon::parse($liveSession->scheduled_at);
            $endTime = $startTime->copy()->addHours(2); // Durasi 2 jam
            
            $event->name = "Live Session: " . $liveSession->asset->name;
            $event->description = "Host: " . $liveSession->user->name . "\n" .
                                  "Platform: " . $liveSession->asset->platform;
            $event->startDateTime = $startTime;
            $event->endDateTime = $endTime;
            
            $savedEvent = $event->save();
            
            Log::info('Google Calendar Event Created', [
                'event_id' => $savedEvent->id,
                'live_session_id' => $liveSession->id
            ]);
            
            return [
                'success' => true,
                'event_id' => $savedEvent->id,
                'event' => $savedEvent
            ];
            
        } catch (\Exception $e) {
            Log::error('Google Calendar Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update event di Google Calendar
     */
    public function updateEvent($eventId, $data)
    {
        try {
            $event = Event::find($eventId);
            
            if (isset($data['scheduled_at'])) {
                $startTime = Carbon::parse($data['scheduled_at']);
                $endTime = $startTime->copy()->addHours(2);
                
                $event->startDateTime = $startTime;
                $event->endDateTime = $endTime;
            }
            
            if (isset($data['status'])) {
                $event->description .= "\nStatus: " . $data['status'];
            }
            
            $event->save();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Update Calendar Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Hapus event dari Google Calendar
     */
    public function deleteEvent($eventId)
    {
        try {
            $event = Event::find($eventId);
            $event->delete();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            Log::error('Delete Calendar Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}