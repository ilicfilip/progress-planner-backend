<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Registered Sites') }}
            </h2>
            <form method="POST" action="{{ route('registered-sites.refetch') }}">
                @csrf
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Refetch Data
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showModal: false, modalData: {} }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Registered Sites ({{ $sites->count() }})</h3>

                    @if($sites->isEmpty())
                        <p class="text-gray-600 dark:text-gray-400">No sites found. Click "Refetch Data" to load data from Progress Planner.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site URL</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">License Key</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Plugin Version</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Emailed</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">API Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">API Endpoint</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($sites as $site)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <a href="{{ $site->site_url }}" target="_blank" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $site->site_url }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-xs">
                                                {{ $site->license_key ? Str::limit($site->license_key, 20) : 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $site->siteStat?->plugin_version ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $site->last_emailed_date?->format('Y-m-d') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($site->siteStat?->api_available)
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                        Available
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                        Failed
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($site->license_key && $site->siteStat?->api_available)
                                                    @php
                                                        $apiUrl = rtrim($site->site_url, '/') . '/wp-json/?rest_route=/progress-planner/v1/get-stats/' . $site->license_key;
                                                    @endphp
                                                    <a href="{{ $apiUrl }}" target="_blank" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        Endpoint
                                                    </a>
                                                @else
                                                    <span class="text-gray-500 dark:text-gray-400">Failed</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <button
                                                    @click="showModal = true; modalData = {{ json_encode([
                                                        'site_url' => $site->site_url,
                                                        'license_key' => $site->license_key,
                                                        'last_emailed_at' => $site->last_emailed_at,
                                                        'last_emailed_date' => $site->last_emailed_date?->format('Y-m-d'),
                                                        'api_available' => $site->siteStat?->api_available ?? false,
                                                        'plugin_version' => $site->siteStat?->plugin_version,
                                                        'last_fetched_at' => $site->siteStat?->last_fetched_at?->format('Y-m-d H:i:s'),
                                                        'error_message' => $site->siteStat?->error_message,
                                                        'raw_data' => $site->raw_data,
                                                        'raw_response' => $site->siteStat?->raw_response,
                                                    ]) }}"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 underline"
                                                >
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div
            x-show="showModal"
            x-cloak
            @click.away="showModal = false"
            class="fixed inset-0 z-50 overflow-y-auto"
            style="display: none;"
        >
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

            <!-- Modal Content -->
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Site Details
                        </h3>
                        <button
                            @click="showModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 overflow-y-auto max-h-[70vh]">
                        <!-- Site Information -->
                        <div class="mb-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Site Information</h4>
                            <dl class="grid grid-cols-1 gap-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Site URL</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a :href="modalData.site_url" target="_blank" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" x-text="modalData.site_url"></a>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">License Key</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono" x-text="modalData.license_key || 'N/A'"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Emailed At (YYYYWW)</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100" x-text="modalData.last_emailed_at || 'N/A'"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Emailed Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100" x-text="modalData.last_emailed_date || 'N/A'"></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- API Stats -->
                        <div class="mb-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">API Status</h4>
                            <dl class="grid grid-cols-1 gap-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">API Available</dt>
                                    <dd class="mt-1">
                                        <span :class="modalData.api_available ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'" class="px-2 py-1 text-xs font-semibold rounded-full" x-text="modalData.api_available ? 'Available' : 'Failed'"></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Plugin Version</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100" x-text="modalData.plugin_version || 'N/A'"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Fetched At</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100" x-text="modalData.last_fetched_at || 'N/A'"></dd>
                                </div>
                                <div x-show="modalData.error_message">
                                    <dt class="text-sm font-medium text-red-500 dark:text-red-400">Error Message</dt>
                                    <dd class="mt-1 text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 p-3 rounded" x-text="modalData.error_message"></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Raw Response Data -->
                        <div class="mb-6" x-show="modalData.raw_response">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">API Response Data</h4>
                            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto">
                                <pre class="text-xs text-gray-900 dark:text-gray-100" x-text="JSON.stringify(modalData.raw_response, null, 2)"></pre>
                            </div>
                        </div>

                        <!-- Raw Registration Data -->
                        <div class="mb-6" x-show="modalData.raw_data">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Registration Data</h4>
                            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto">
                                <pre class="text-xs text-gray-900 dark:text-gray-100" x-text="JSON.stringify(modalData.raw_data, null, 2)"></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end p-6 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="showModal = false"
                            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
