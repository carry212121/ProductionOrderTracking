<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <span class="text-gray-800 font-medium">รายการ Proforma Invoice</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Excel Upload Modal -->
    <div id="excelUploadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative">
            <!-- Close button -->
            <button onclick="document.getElementById('excelUploadModal').classList.add('hidden')"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>

            <!-- Title -->
            <h3 class="text-xl font-semibold mb-4 text-center">อัปโหลดไฟล์</h3>

            <!-- Body -->
            <form method="POST" action="{{ route('proformaInvoice.importExcel') }}" enctype="multipart/form-data" class="flex flex-col items-center">
                @csrf

                <!-- Excel icon -->
                <div class="text-6xl text-green-600 mb-2">
                    📄
                </div>

                <!-- File name display -->
                <div id="selectedFileName" class="text-sm text-gray-700 mb-2 h-5 text-center truncate w-full">
                    ยังไม่ได้เลือกไฟล์
                </div>

                <!-- Upload area -->
                <div class="text-center text-gray-600 mb-6 text-sm">
                    ลากและวางไฟล์ที่นี่ หรือ 
                    <label for="excelUploadInput" class="text-blue-600 underline cursor-pointer">
                        เลือกไฟล์
                    </label>
                    <input id="excelUploadInput" type="file" name="excel_file" accept=".xlsx,.xls" required class="hidden">
                </div>

                <!-- Buttons -->
                <div class="flex justify-between w-full mt-4">
                    <button type="button" onclick="document.getElementById('excelUploadModal').classList.add('hidden')"
                            class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800">
                        ยกเลิก
                    </button>
                    <!-- 🆕 Preview button -->
                    <button type="button" onclick="submitPreviewForm()"
                            class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                        🔍 ดูตัวอย่าง
                    </button>
                    <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">
                        อัปโหลด
                    </button>
                </div>
            </form>
            <form id="previewForm" method="POST" action="{{ route('proformaInvoice.preview') }}" enctype="multipart/form-data" style="display: none;">
                @csrf
                <input type="file" name="excel_file" id="previewExcelFileInput">
            </form>
        </div>
    </div>


    
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการ Proforma Invoice ของ {{auth()->user()->name}}
        </h2>

        <div class="flex items-center gap-2">
            <!-- Filter dropdown -->
            <x-filter toggleId="filterToggleBtn" panelId="filterPanel">
                <button class="filter-option w-full text-left hover:bg-gray-100 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                {{-- <button class="filter-option w-full text-left hover:bg-green-100 px-2 py-1" data-filter="finished">✅ เสร็จแล้ว</button> --}}
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="late">🔴 PIเลท</button>
            </x-filter>
            <!-- Upload Excel Icon Button -->
            <button 
                onclick="document.getElementById('excelUploadModal').classList.remove('hidden')" 
                class="p-2 rounded-full shadow relative group hover:bg-green-100"
                title="อัปโหลด Excel"
            >
                <img src="https://www.svgrepo.com/show/373589/excel.svg" alt="Upload Excel" class="w-8 h-8" />
                <span class="absolute hidden group-hover:block text-sm bg-black text-white rounded px-2 py-1 bottom-full mb-2 left-1/2 -translate-x-1/2 whitespace-nowrap">
                    อัปโหลด Excel
                </span>
            </button>


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
                        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
                        $today = \Carbon\Carbon::today();
                        $scheduledDate = \Carbon\Carbon::parse($pi->ScheduleDate);

                        $totalCount = $pi->products->count();
                        $finishedCount = $pi->products->where('Status', 'Finish')->count();

                        $lateCount = 0;

                        foreach ($pi->products as $product) {
                            if ($product->Status === 'Finish') continue;

                            $isLate = false;
                            $jobControls = $product->jobControls->keyBy('Process');
                            $latestProcess = null;

                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $isLate = true;
                                }
                            }

                            // fallback if no job control late
                            if (!$isLate && $pi->ScheduleDate && $scheduledDate->lt($today)) {
                                $isLate = true;
                            }

                            if ($isLate) $lateCount++;
                        }

                        $cardClass = 'bg-white border border-gray-200';
                        if ($lateCount > 0) {
                            $cardClass = 'bg-red-100 border border-red-400';
                        }
                    @endphp
                        <a 
                            href="{{ 
                                auth()->user()->role === 'Head' 
                                    ? route('products.list', ['id' => $pi->id]) 
                                    : route('proformaInvoice.show', $pi->id) 
                            }}"
                            class="block hover:shadow-lg transition duration-300 pi-card"
                            data-pi="{{ $pi->PInumber }}"
                            data-customer="{{ $pi->byOrder }}"
                            data-po="{{ $pi->CustomerPO }}"
                            data-status="{{ $lateCount > 0 ? 'late' : ($finishedCount === $totalCount ? 'finished' : 'inprogress') }}"
                        >
                        <div class="{{ $cardClass }} rounded-lg shadow p-5">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-lg font-bold text-indigo-600">
                                    รหัส PI: {{ $pi->PInumber }}
                                </h3>
                                @if($finishedCount === $totalCount && $totalCount > 0)
                                    <span class="inline-block bg-green-500 text-white text-xs font-semibold px-2 py-1 rounded">
                                        ✅ Finish
                                    </span>
                                @endif
                            </div>
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
                                    <span class="font-semibold">พนักงานขาย:</span> {{ $pi->Salesperson?->name ?? '-' }}
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
        document.getElementById('excelUploadInput').addEventListener('change', function (event) {
            const fileNameDisplay = document.getElementById('selectedFileName');
            const file = event.target.files[0];
            fileNameDisplay.textContent = file ? file.name : '';
        });
        function submitPreviewForm() {
            const fileInput = document.getElementById('excelUploadInput');
            const previewInput = document.getElementById('previewExcelFileInput');
            const file = fileInput.files[0];

            if (!file) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกไฟล์ก่อนดูตัวอย่าง',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            // Clone file to hidden form
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            previewInput.files = dataTransfer.files;

            document.getElementById('previewForm').submit();
        }

    </script>
    @if(session('excel_success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '{{ session('excel_success') }}',
            confirmButtonText: 'ตกลง',
            timer: 3000
        });
    </script>
    @endif
    @if(session('excel_error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'นำเข้าข้อมูลล้มเหลว',
            text: '{{ session('excel_error') }}',
            confirmButtonText: 'ตกลง'
        });
    </script>
    @endif
    
</x-app-layout>
