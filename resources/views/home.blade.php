<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Total Registered Sites -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Total Registered Sites
                                </p>
                                <p class="mt-2 text-4xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($totalSites) }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Active Sites -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Total Active Sites
                                </p>
                                <p class="mt-2 text-4xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($activeSites) }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $totalSites > 0 ? round(($activeSites / $totalSites) * 100, 1) : 0 }}% of total
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plugin Version Distribution -->
            @if($activeSites > 0 && $versionData->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                            Plugin Version Distribution (Active Sites)
                        </h3>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Pie Chart -->
                            <div class="flex items-center justify-center">
                                @php
                                    $colors = [
                                        '#3B82F6', // blue
                                        '#10B981', // green
                                        '#F59E0B', // amber
                                        '#EF4444', // red
                                        '#8B5CF6', // purple
                                        '#EC4899', // pink
                                        '#06B6D4', // cyan
                                        '#F97316', // orange
                                    ];

                                    $gradientStops = [];
                                    $currentAngle = 0;

                                    foreach ($versionData as $index => $data) {
                                        $color = $colors[$index % count($colors)];
                                        $percentage = $data['percentage'];
                                        $angle = ($percentage / 100) * 360;

                                        $gradientStops[] = "$color {$currentAngle}deg " . ($currentAngle + $angle) . "deg";
                                        $currentAngle += $angle;
                                    }

                                    $gradient = implode(', ', $gradientStops);
                                @endphp

                                <div class="relative" style="width: 256px; height: 256px;">
                                    <div
                                        class="rounded-full shadow-lg"
                                        style="width: 256px; height: 256px; background: conic-gradient({{ $gradient }}); border: 2px solid rgba(0,0,0,0.1);"
                                    ></div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="rounded-full bg-white dark:bg-gray-800 shadow-inner flex items-center justify-center" style="width: 128px; height: 128px;">
                                            <div class="text-center">
                                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                    {{ $versionData->count() }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $versionData->count() === 1 ? 'Version' : 'Versions' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="flex flex-col justify-center space-y-3">
                                @foreach($versionData as $index => $data)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-4 h-4 rounded-full flex-shrink-0"
                                                style="background-color: {{ $colors[$index % count($colors)] }}"
                                            ></div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $data['version'] === 'Unknown/Not Detected' ? $data['version'] : 'Version ' . $data['version'] }}
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                {{ $data['percentage'] }}%
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                                ({{ $data['count'] }} sites)
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($activeSites > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center">
                        <p class="text-gray-500 dark:text-gray-400">
                            Plugin version data is being collected. Refresh the page to see updated statistics.
                        </p>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center">
                        <p class="text-gray-500 dark:text-gray-400">
                            No active sites found. Click "Registered Sites" and use "Refetch Data" to fetch site information.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
