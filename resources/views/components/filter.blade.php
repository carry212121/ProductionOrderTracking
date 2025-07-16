@props(['toggleId', 'panelId'])

<div class="relative">
    {{-- Toggle Button --}}
    <button id="{{ $toggleId }}" data-panel-id="{{ $panelId }}" class="p-2 rounded hover:bg-gray-200" title="ตัวกรอง">
        <img src="https://www.svgrepo.com/show/505380/filters-2.svg" alt="Filter Icon" class="w-12 h-12">
    </button>

    {{-- Filter Panel --}}
    <div id="{{ $panelId }}"
        class="absolute right-0 mt-2 z-50 bg-white border border-gray-300 shadow-md rounded-md w-64 transition-all duration-300 opacity-0 scale-95 pointer-events-none">
        <div class="p-4 space-y-2">
            {{ $slot }}
        </div>
    </div>
</div>
