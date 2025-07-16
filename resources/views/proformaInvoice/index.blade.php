<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <span class="text-gray-800 font-medium">รายการ Proforma Invoice</span>
        </nav>
    </x-slot>
    
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการ Proforma Invoice ของ {{auth()->user()->name}}
        </h2>

        <div class="flex items-center gap-2">
            <!-- Filter dropdown -->
            <x-filter toggleId="filterToggleBtn" panelId="filterPanel">
                <button class="filter-option w-full text-left hover:bg-gray-100 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                {{-- <button class="filter-option w-full text-left hover:bg-green-100 px-2 py-1" data-filter="finished">✅ เสร็จแล้ว</button> --}}
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="late">🔴 สินค้าเลท</button>
            </x-filter>

            <!-- Search bar -->
            <x-search-bar id="pi-search" placeholder="ค้นหา PI/ลูกค้า/PO..." class="w-64" />
        </div>
    </div>
    <div id="no-results" class="text-gray-500 text-center my-4 hidden">
        ไม่พบรายการPIที่ตรงกับตัวกรอง
    </div>

    <div class="py-6 px-6">
        {{-- <div class="max-h-[calc(2*280px)] overflow-y-auto pr-2"> --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($pis as $pi)
                    @php
                        $totalCount = $pi->products->count();
                        $finishedCount = $pi->products->where('Status', 'Finish')->count();
                        $lateCount = 0;

                        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
                        $today = \Carbon\Carbon::today();

                        foreach ($pi->products as $product) {
                            // ✅ Skip finished products
                            if ($product->Status === 'Finish') {
                                continue;
                            }

                            $jobControls = $product->jobControls->keyBy('Process');

                            // ✅ Find the latest assigned process
                            $latestProcess = null;
                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            // ✅ If ScheduleDate of current process is before today, count as late
                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $lateCount++;
                                }
                            }
                        }
                        $cardClass = 'bg-white border border-gray-200';
                        if ($lateCount > 0) {
                            $cardClass = 'bg-red-100 border border-red-400';
                        }
                    @endphp
                    <a 
                        href="{{ route('proformaInvoice.show', $pi->id) }}" 
                        class="block hover:shadow-lg transition duration-300 pi-card"
                        data-pi="{{ $pi->PInumber }}"
                        data-customer="{{ $pi->byOrder }}"
                        data-po="{{ $pi->CustomerPO }}"
                        data-status="{{ $lateCount > 0 ? 'late' : ($finishedCount === $totalCount ? 'finished' : 'inprogress') }}"
                    >
                        <div class="{{ $cardClass }} rounded-lg shadow p-5">
                            <h3 class="text-lg font-bold text-indigo-600 mb-2">
                                รหัส PI: {{ $pi->PInumber }}
                            </h3>
                            {{-- Row 1: ชื่อลูกค้า + รหัส PO --}}
                            <div class="flex justify-between mb-2">
                                <p class="w-1/2 pr-2 whitespace-nowrap overflow-hidden text-ellipsis">
                                    <span class="font-semibold">ชื่อลูกค้า:</span> {{ $pi->byOrder }}
                                </p>
                                <p class="w-1/2 whitespace-nowrap overflow-hidden text-ellipsis">
                                    <span class="font-semibold">รหัส PO:</span> {{ $pi->CustomerPO }}
                                </p>
                            </div>

                            {{-- Row 2: รหัสลูกค้า + พนักงานขาย --}}
                            <div class="flex justify-between mb-2">
                                <p class="w-1/2 pr-2 whitespace-nowrap overflow-hidden text-ellipsis">
                                    <span class="font-semibold">รหัสลูกค้า:</span> {{ $pi->CustomerID }}
                                </p>
                                <p class="w-1/2 whitespace-nowrap overflow-hidden text-ellipsis">
                                    <span class="font-semibold">พนักงานขาย:</span> {{ $pi->Salesperson->name }}
                                </p>
                            </div>
                            <div class="mb-2">
                                <p><span class="font-semibold">จำนวนสินค้าที่เสร็จแล้ว:</span> {{ $finishedCount }} / {{ $totalCount }} รายการ</p>
                            </div>
                            <div class="mb-2">
                            <p><span class="font-semibold">จำนวนสินค้าที่เลท:</span> {{ $lateCount }} / {{ $totalCount }} รายการ</p>
                            </div>
                            <p><span class="font-semibold">วันกำหนดรับ:</span> 
                                {{ \Carbon\Carbon::parse($pi->ScheduleDate)->format('d-m-Y') }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="text-gray-500 col-span-3">ไม่มีรายการ PI สำหรับคุณ</div>
                @endforelse
            </div>
        {{-- </div> --}}
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('pi-search');
            const cards = document.querySelectorAll('.pi-card');
            const filterButtons = document.querySelectorAll('.filter-option');

            function updateNoResultsMessage() {
                const visibleCards = Array.from(cards).filter(c => c.style.display !== 'none');
                document.getElementById('no-results').style.display = visibleCards.length === 0 ? 'block' : 'none';
            }

            searchInput?.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase();
                cards.forEach(card => {
                    const text = (
                        card.dataset.pi + ' ' +
                        card.dataset.customer + ' ' +
                        card.dataset.po
                    ).toLowerCase();

                    card.style.display = text.includes(query) ? '' : 'none';
                });
                updateNoResultsMessage();
            });

            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const filter = btn.dataset.filter;

                    cards.forEach(card => {
                        const status = card.dataset.status;

                        if (filter === 'all') {
                            card.style.display = '';
                        } else {
                            card.style.display = status === filter ? '' : 'none';
                        }
                    });

                    updateNoResultsMessage();
                });
            });

            updateNoResultsMessage(); // Call once initially
        });

    </script>

</x-app-layout>
