<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Live Stream Dashboard - ffMix</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {},
                },
            }
        </script>
    </head>
    <body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-300">
        <div class="container mx-auto py-8 px-4">
            
            <header class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Live Stream Dashboard</h1>
                    <p class="text-gray-600 dark:text-gray-400">ffMix Live Stream Management</p>
                </div>
                
                <div class="flex items-center space-x-3">
                    <button wire:click="startRecording" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18v-6a4 4 0 118 0v6m0 0H6.066A4 4 0 011 16.066A13.97 13.97 0 0016.85 11.314A14.98 14.98 0 0024 8c0-4.411-3.582-8-8-8s-8 3.582-8 8M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </path>
                    </svg>
                    Start Recording
                    </button>
                    
                    <button wire:click="stopRecording" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                        </path>
                    </svg>
                    Stop Recording
                    </button>
                </div>
            </header>
            
            @if($recordingActive)
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg px-4 py-3 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </path>
                    </svg>
                    <span class="font-medium">Recording in progress...</span>
                </div>
            @endif
            
            <section class="space-y-6">
                
                <div class="rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Stream Information</h2>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 mb-1">Source URL</p>
                            <p class="text-xs truncate text-gray-600 dark:text-gray-400 break-all">{{$streamUrl ?? 'Not set'}}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 mb-1">Stream Type</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{$streamType ?? 'N/A'}}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 mb-1">Status</p>
                            <span class="px-2 py-1 text-xs rounded 
                                @if($status === 'completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @elseif($status === 'running') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                                {{$status ?? 'pending'}}
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 mb-1">Duration</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{$duration ?? 0}} seconds</p>
                        </div>
                    </div>
                </div>
                
                @if($recordedOutput)
                    <div class="rounded-lg p-4 border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900">
                        <h2 class="font-semibold text-green-900 dark:text-green-200 mb-3">Recorded Output</h2>
                        
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </path>
                            </svg>
                            </div>
                            <div>
                                <p class="font-medium text-green-800 dark:text-green-200 truncate">{{$recordedOutput}}</p>
                                <p class="text-xs text-green-600 dark:text-gray-400">{{$recordedDuration}} seconds</p>
                            </div>
                        </div>
                        
                        <button wire:click="downloadRecording" class="mt-2 text-sm text-green-600 dark:text-green-400 underline underline-offset-2 hover:text-green-700">
                            Download MP4
                        </button>
                    </div>
                @endif
                
                @if($dskTemplate && $dskTemplate !== 'none')
                    <div class="rounded-lg p-4 border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900">
                        <h2 class="font-semibold text-blue-900 dark:text-blue-200 mb-3">DSK Overlay</h2>
                        
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 mb-1">Template</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{$dskTemplate}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 mb-1">Title</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{$dskTitle ?? 'N/A'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 mb-1">Subtitle</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{$dskSubtitle ?? 'N/A'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 mb-1">Accent</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{$dskAccent ?? '#06b6d4'}}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
            </section>
            
            <footer class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400 text-center">
                <p>ffMix Live Stream Management - Built with Laravel Livewire</p>
            </footer>
            
        </div>
        
        @livewireScripts
    </body>
</html>