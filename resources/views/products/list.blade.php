<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('proformaInvoice.index') }}" class="hover:underline text-blue-600">รายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าของ {{ $pi->PInumber }}</span>
        </nav>
    </x-slot>
        @php
        $scheduleDate = $pi->ScheduleDate ? \Carbon\Carbon::parse($pi->ScheduleDate)->startOfDay() : null;
        $today = \Carbon\Carbon::today();
        $dayDiff = $scheduleDate ? $scheduleDate->diffInDays($today) : null;
        $dayDiff = abs($dayDiff);
        $isOverdue = $scheduleDate && $today->gt($scheduleDate);
        $allFinished = $pi->products->every(fn($p) => $p->Status === 'Finish');
    @endphp
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการสินค้าของ Proforma Invoice: {{ $pi->PInumber }}
        </h2>
        <p>ชื่อ Production: {{$pi->user->name}}</p>
        <p>ชื่อ พนักงานขาย: {{$pi->salesPerson->name}}</p>
        <div class="col-span-1 space-y-1 text-gray-700">
            <p><strong>วันกำหนดรับ:</strong> {{ $scheduleDate ? $scheduleDate->format('d-m-Y') : '-' }}</p>
            @if ($scheduleDate)
                <p>
                    <strong>สถานะ :</strong>
                    @if ($allFinished)
                        <span class="text-green-600">✅ เสร็จสิ้นแล้ว</span>
                    @else
                        @if ($isOverdue)
                            <span class="text-red-600">เลยกำหนดมาแล้ว {{ $dayDiff }} วัน</span>
                        @else
                            เหลืออีก {{ $dayDiff }} วัน
                        @endif
                    @endif
                </p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <x-filter toggleId="filterToggleBtn" panelId="filterPanel">
                <button class="filter-option w-full text-left hover:bg-gray-200 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                <button class="filter-option w-full text-left hover:bg-yellow-100 px-2 py-1" data-filter="yellow">🟡 ช้า 1–7 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="red">🔴 ช้า 8–14 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-400 px-2 py-1" data-filter="darkred">🟥 ช้าเกิน 15 วัน</button>
            </x-filter>
            <x-search-bar id="product-search" placeholder="ค้นหารหัสสินค้า/สินค้าลูกค้า..." class="w-64" />
        </div>
    </div>
    <div class="flex items-center px-6 gap-4 text-sm text-gray-700">
        <span>🟥 ล่าช้าเกิน 15 วัน: <strong>{{ $lateDarkRed }}</strong></span>
        <span>🔴 ล่าช้า 8–14 วัน: <strong>{{ $lateRed }}</strong></span>
        <span>🟡 ล่าช้า 1–7 วัน: <strong>{{ $lateYellow }}</strong></span>
    </div>
    <div id="no-result-message" class="text-center text-gray-500 mt-6 hidden">
        ไม่พบรหัสสินค้า/สินค้าลูกค้าที่ค้นหา
    </div>
    <div id="no-results" class="text-gray-500 text-center my-4 hidden">
        ไม่พบรายการสินค้าที่ตรงกับตัวกรอง
    </div>
    <div class="max-w-6xl mx-auto p-6">
        <div class="space-y-4 h-[400px] overflow-y-auto border rounded-lg p-4 bg-gray-50">
            @foreach($pi->products as $product)
                {{-- @php $bgClass = $product->lateClass ?: 'bg-white'; @endphp --}}
                <div class="product-card"
                    data-product-number="{{ $product->ProductNumber }}"
                    data-customer-number="{{ $product->ProductCustomerNumber }}"
                    data-created-at="{{ $product->created_at }}">
                    <a href="{{ route('products.detail', ['pi_id' => $pi->id, 'product_id' => $product->id]) }}"
                    class="block w-full border rounded-lg shadow hover:shadow-md transition duration-300 bg-white p-4"
                    >
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div class="text-lg font-semibold text-gray-800 min-w-[150px] flex items-center gap-2">
                                {{-- Tag in front of card --}}
                                @if ($product->latestProcessName)
                                    <span class="inline-block w-4 h-4 rounded-full border {{ $product->latestProcessLateClass }}"></span>
                                @endif
                                {{-- Product Number --}}
                                <div class="text-lg font-semibold text-gray-800 min-w-[150px]">
                                    รหัสสินค้า: {{ $product->ProductNumber }}
                                </div>
                            </div>
                            {{-- Process + Diff Days --}}
                            <div class="flex flex-wrap gap-3 text-sm text-gray-700">
                                @if (!empty($product->processDays))
                                    @foreach ($product->processDays as $proc)
                                        @php
                                            $highlightClass = $proc['lateClass'] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <div class="flex items-center gap-1 px-3 py-1 rounded-full border {{ $highlightClass }}">
                                            <span class="font-medium">{{ $proc['name'] }}</span>
                                            <span class="text-xs">({{ $proc['days'] }} วัน)</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-gray-400 italic">ยังไม่มีขั้นตอน</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
