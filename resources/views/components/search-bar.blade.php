@props([
    'id' => 'search-input',
    'placeholder' => 'ค้นหา...',
    'class' => '',
])

<div class="relative mb-4 {{ $class }}">
    {{-- Search Icon --}}
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.15-5.4a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </div>

    <input
        type="text"
        id="{{ $id }}"
        placeholder="{{ $placeholder }}"
        class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded"
    >
</div>
