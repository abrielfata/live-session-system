<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard Manager
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Statistik -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-100 p-4 rounded-lg">
                    <h3 class="font-bold text-lg">Scheduled</h3>
                    <p class="text-3xl">{{ $totalScheduled }}</p>
                </div>
                <div class="bg-green-100 p-4 rounded-lg">
                    <h3 class="font-bold text-lg">Completed</h3>
                    <p class="text-3xl">{{ $totalCompleted }}</p>
                </div>
                <div class="bg-red-100 p-4 rounded-lg">
                    <h3 class="font-bold text-lg">Cancelled</h3>
                    <p class="text-3xl">{{ $totalCancelled }}</p>
                </div>
            </div>

            <!-- Tombol Buat Jadwal -->
            <div class="mb-4">
                <a href="{{ route('schedules.create') }}" 
                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    + Buat Jadwal Baru
                </a>
            </div>

            <!-- Tabel Jadwal -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-bold text-lg mb-4">Semua Live Session</h3>
                    
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-2">ID</th>
                                <th class="px-4 py-2">Host</th>
                                <th class="px-4 py-2">Asset</th>
                                <th class="px-4 py-2">Platform</th>
                                <th class="px-4 py-2">Jadwal</th>
                                <th class="px-4 py-2">GMV</th>
                                <th class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr class="border-b {{ $session->status === 'cancelled' ? 'bg-red-50' : '' }}">
                                    <td class="px-4 py-2">{{ $session->id }}</td>
                                    <td class="px-4 py-2">{{ $session->user->name }}</td>
                                    <td class="px-4 py-2">{{ $session->asset->name }}</td>
                                    <td class="px-4 py-2">{{ $session->asset->platform }}</td>
                                    <td class="px-4 py-2">{{ $session->scheduled_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        {{ $session->host_reported_gmv ? 'Rp ' . number_format($session->host_reported_gmv, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded text-xs
                                            {{ $session->status === 'scheduled' ? 'bg-blue-200' : '' }}
                                            {{ $session->status === 'completed' ? 'bg-green-200' : '' }}
                                            {{ $session->status === 'cancelled' ? 'bg-red-200' : '' }}">
                                            {{ ucfirst($session->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $sessions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>