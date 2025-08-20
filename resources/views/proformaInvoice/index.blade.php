<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <span class="text-gray-800 font-medium">รายการ Proforma Invoice</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

    <!-- Excel Upload Modal -->
    <div id="excelUploadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-3xl min-h-[400px] flex flex-col justify-between relative">
            
            <!-- Title -->
            <h3 class="text-xl font-semibold mb-4 text-center pb-2">อัปโหลดไฟล์</h3>

            <!-- Body with full box line -->
            <div class="flex-grow px-4">
                <div class="border border-gray-300 rounded p-6 flex flex-col items-center">
                    <!-- Excel icon -->
                    <div class="text-6xl text-green-600 mb-2">
                        📄
                    </div>

                    <!-- File name display -->
                    <div id="selectedFileName" class="text-sm text-gray-700 mb-2 h-5 text-center truncate w-full">
                        ยังไม่ได้เลือกไฟล์
                    </div>

                    <!-- Upload area -->
                    <div class="drop-area text-center text-gray-600 text-sm border-dashed border-2 border-gray-300 rounded w-full py-6 cursor-pointer transition">
                        ลากและวางไฟล์ที่นี่ หรือ 
                        <label for="excelUploadInput" class="text-blue-600 underline cursor-pointer">
                            เลือกไฟล์
                        </label>
                        <form id="excelUploadForm" method="POST" action="{{ route('proformaInvoice.importExcel') }}" enctype="multipart/form-data">
                            @csrf
                            <input id="excelUploadInput" type="file" name="excel_file" accept=".xlsx,.xls" class="hidden" {{ empty($resume) ? 'required' : '' }}>
                            <input type="hidden" name="excel_token" id="excelTokenUpload" value="{{ $resume['token'] ?? '' }}">
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bottom Buttons -->
            <div class="flex justify-between w-full mt-4 text-sm text-blue-600 underline px-4">
                <button type="button"
                        onclick="closeUploadModal()"
                        class="hover:text-blue-800">
                    ❌ ยกเลิก
                </button>
                <button type="button"
                        onclick="submitPreviewForm()"
                        class="hover:text-blue-800">
                    🔍 ดูตัวอย่าง
                </button>
                <button type="submit"
                        form="excelUploadForm"
                        class="hover:text-blue-800">
                    ⬆️ อัปโหลด
                </button>
            </div>

            <!-- Hidden Preview Form -->
            <form id="previewForm" method="POST" action="{{ route('proformaInvoice.preview') }}" enctype="multipart/form-data" style="display:none;">
                @csrf
                <input type="file" name="excel_file" id="previewExcelFileInput">
                <input type="hidden" name="excel_token" id="excelTokenPreview" value="{{ $resume['token'] ?? '' }}">
            </form>
        </div>
    </div>
    <!-- Edit PI Modal -->
    <div id="editPIModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white w-full max-w-xl rounded-lg shadow-lg p-6 relative">
        <button type="button" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                onclick="closeEditPIModal()">✖</button>

        <h3 class="text-lg font-semibold mb-4 text-center">แก้ไข Proforma Invoice</h3>

        <form id="editPIForm" method="POST" action="">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Row 0 (full width): PI number (read-only) --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">เลขที่ PI</label>
            <div id="piNumberText" class="bg-gray-50 border border-gray-300 rounded px-3 py-2 text-gray-700"></div>
        </div>

        {{-- Row 1: รหัสลูกค้า + พนักงานขาย (both read-only) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสลูกค้า</label>
            <div id="customerIdText" class="bg-gray-50 border border-gray-300 rounded px-3 py-2 text-gray-700"></div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">พนักงานขาย</label>
            <div id="salespersonNameText" class="bg-gray-50 border border-gray-300 rounded px-3 py-2 text-gray-700"></div>
        </div>

        {{-- Row 2: ชื่อลูกค้า + รหัส PO (both editable) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า</label>
            <input type="text" name="byOrder" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัส PO</label>
            <input type="text" name="CustomerPO" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Row 3: วันสั่ง + วันกำหนดรับ (flatpickr visible + hidden real inputs) --}}
        <div class="flatpickr-wrapper" data-input>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันสั่ง</label>
            <div class="flex items-center border p-1 w-full rounded">
            <input type="text" id="OrderDate_display" class="flatpickr w-full px-2 py-1" data-input readonly>
            <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
            </div>
        </div>
        <div class="flatpickr-wrapper" data-input>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันกำหนดรับ</label>
            <div class="flex items-center border p-1 w-full rounded">
            <input type="text" id="ScheduleDate_display" class="flatpickr w-full px-2 py-1" data-input readonly>
            <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
            </div>
        </div>

        {{-- Hidden actual fields (Y-m-d) --}}
        <input type="hidden" name="OrderDate" id="OrderDate">
        <input type="hidden" name="ScheduleDate" id="ScheduleDate">
        </div>

        <div class="flex justify-between mt-6">
            <button type="button" onclick="closeEditPIModal()" class="text-blue-600 hover:underline">ยกเลิก</button>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">บันทึก</button>
        </div>
        </form>
    </div>
    </div>

    
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            @if (auth()->user()?->role === 'Head' || auth()->user()?->role === 'Admin')
                รายการ Proforma Invoice ทั้งหมด
            @else
                รายการ Proforma Invoice ของ {{ auth()->user()->name }}
            @endif
        </h2>

        <div class="flex items-center gap-2">
            <!-- Filter dropdown -->
            <x-filter toggleId="filterToggleBtn" panelId="filterPanel">
                <button class="filter-option w-full text-left hover:bg-gray-100 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                {{-- <button class="filter-option w-full text-left hover:bg-green-100 px-2 py-1" data-filter="finished">✅ เสร็จแล้ว</button> --}}
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="late">🔴 PIเลท</button>
            </x-filter>
            @php
                $role = Auth::user()->role;
            @endphp

            @if ($role === 'Admin' || $role === 'Head')
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
            @endif

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
                    $cardClass = $pi->cardClass;
                    $finishedCount = $pi->finishedCount;
                    $lateCount = $pi->lateCount;
                    $totalCount = $pi->products->count();
                    $to = auth()->user()->role === 'Head'
                        ? route('products.list', ['id' => $pi->id])
                        : route('proformaInvoice.show', $pi->id);
                @endphp

                <!-- Whole card clickable -->
                <div
                    class="pi-card rounded-lg shadow p-5 transition duration-300 hover:shadow-lg hover:scale-[1.02] cursor-pointer {{ $cardClass }}"
                    onclick="window.location.href='{{ $to }}'"
                    data-pi="{{ $pi->PInumber }}"
                    data-customer="{{ $pi->byOrder }}"
                    data-po="{{ $pi->CustomerPO }}"
                    data-status="{{ $lateCount > 0 ? 'late' : ($finishedCount === $totalCount ? 'finished' : 'inprogress') }}"
                >
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-bold text-indigo-600">
                            รหัส PI: {{ $pi->PInumber }}
                        </h3>

                        @if($finishedCount === $totalCount && $totalCount > 0)
                            <span class="inline-block bg-green-500 text-white text-xs font-semibold px-2 py-1 rounded">
                                ✅ Finish
                            </span>
                        @endif

                        @php $createdDaysAgo = \Carbon\Carbon::parse($pi->created_at)->diffInDays(today()); @endphp
                        @if($createdDaysAgo <= 2)
                            <span class="inline-block bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded">
                                🆕 New
                            </span>
                        @endif
                    </div>

                    <div class="flex justify-between mb-2">
                        <p class="w-1/2 pr-2 truncate"><span class="font-semibold">ชื่อลูกค้า:</span> {{ $pi->byOrder }}</p>
                        <p class="w-1/2 truncate"><span class="font-semibold">รหัส PO:</span> {{ $pi->CustomerPO }}</p>
                    </div>

                    <div class="flex justify-between mb-2">
                        <p class="w-1/2 pr-2 truncate"><span class="font-semibold">รหัสลูกค้า:</span> {{ $pi->CustomerID }}</p>
                        <p class="w-1/2 truncate"><span class="font-semibold">พนักงานขาย:</span> {{ $pi->Salesperson?->name ?? '-' }}</p>
                    </div>

                    <div class="mb-2">
                        <p><span class="font-semibold">จำนวนสินค้าที่เสร็จแล้ว:</span> {{ $finishedCount }} / {{ $totalCount }} รายการ</p>
                    </div>
                    <div class="mb-2">
                        <p><span class="font-semibold">จำนวนสินค้าที่เลท:</span> {{ $lateCount }} / {{ $totalCount }} รายการ</p>
                    </div>
                    <div class="flex justify-between mb-2">
                        <p class="w-1/2 pr-2 truncate"><span class="font-semibold">วันสั่ง:</span> {{ $pi->OrderDate ? $pi->OrderDate->format('d-m-Y') : '-' }}</p>
                        <p class="w-1/2 truncate"><span class="font-semibold">วันกำหนดรับ:</span> {{ $pi->ScheduleDate ? $pi->ScheduleDate->format('d-m-Y') : '-' }}</p>
                    </div>
                    @if (in_array(auth()->user()?->role, ['Head', 'Admin']))
                        <div class="flex justify-end mt-3 space-x-2">
                            <a href="#"
                            onclick="openEditPIModal(this); event.stopPropagation(); return false;"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded"
                            data-update-url="{{ route('proformaInvoice.update', $pi->id) }}"
                            data-byorder="{{ e($pi->byOrder) }}"
                            data-customerpo="{{ e($pi->CustomerPO) }}"
                            data-orderdate="{{ $pi->OrderDate?->format('Y-m-d') ?? '' }}"
                            data-scheduledate="{{ $pi->ScheduleDate?->format('Y-m-d') ?? '' }}"
                            data-pinumber="{{ e($pi->PInumber) }}"
                            data-customerid="{{ e($pi->CustomerID) }}"
                            data-salespersonname="{{ e($pi->Salesperson?->name ?? '-') }}"
                            >
                            ✏️ แก้ไข
                            </a>

                            <form action="{{ route('proformaInvoice.destroy', $pi->id) }}" method="POST" class="delete-form" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                        onclick="event.stopPropagation(); confirmDelete(this)"
                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                    🗑️ ลบ
                                </button>
                            </form>

                        </div>
                    @endif
                </div>
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

        document.getElementById('excelUploadInput')?.addEventListener('change', function () {
            document.getElementById('excelTokenUpload')?.setAttribute('value','');
            document.getElementById('excelTokenPreview')?.setAttribute('value','');
            const file = this.files[0];
            const nameEl = document.getElementById('selectedFileName');
            if (nameEl) nameEl.textContent = file ? file.name : 'ยังไม่ได้เลือกไฟล์';
        });
        const dropArea = document.querySelector('.drop-area');
        const fileInput = document.getElementById('excelUploadInput');
        const fileNameDisplay = document.getElementById('selectedFileName');

        // Prevent default behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => e.preventDefault());
            dropArea.addEventListener(eventName, e => e.stopPropagation());
        });

        // Highlight drop area
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.add('bg-blue-50', 'border-blue-400');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.remove('bg-blue-50', 'border-blue-400');
            });
        });

        // Handle dropped files
        dropArea.addEventListener('drop', e => {
            const file = e.dataTransfer.files[0];
            if (file && file.name.match(/\.(xls|xlsx)$/)) {
                fileInput.files = e.dataTransfer.files;
                fileNameDisplay.textContent = file.name;
            } else {
                alert("กรุณาเลือกไฟล์ Excel เท่านั้น (.xls, .xlsx)");
            }
        });

        // Handle normal file input
        fileInput.addEventListener('change', function (event) {
            const file = event.target.files[0];
            fileNameDisplay.textContent = file ? file.name : '';
        });
        document.getElementById('excelUploadInput').addEventListener('change', function (event) {
            const fileNameDisplay = document.getElementById('selectedFileName');
            const file = event.target.files[0];
            fileNameDisplay.textContent = file ? file.name : '';
        });
        async function closeUploadModal() {
            const modal        = document.getElementById('excelUploadModal');
            const fileInput    = document.getElementById('excelUploadInput');
            const nameEl       = document.getElementById('selectedFileName');
            const previewInput = document.getElementById('previewExcelFileInput');
            const tokenUpload  = document.getElementById('excelTokenUpload');
            const tokenPreview = document.getElementById('excelTokenPreview');

            // 1) Clear inputs on the client
            fileInput && (fileInput.value = '');
            previewInput && (previewInput.value = '');
            if (nameEl) nameEl.textContent = 'ยังไม่ได้เลือกไฟล์';
            tokenUpload && tokenUpload.setAttribute('value', '');
            tokenPreview && tokenPreview.setAttribute('value', '');

            // 2) Tell server to forget the resume token (and delete temp file)
            try {
                await fetch("{{ route('proformaInvoice.clearUploadStash') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
                });
                console.log('[modal] cleared server resume token');
            } catch (e) {
                console.warn('[modal] failed to clear server token', e);
            }

            // 3) Hide modal
            modal.classList.add('hidden');
        }
        function submitPreviewForm() {
            const fileInput    = document.getElementById('excelUploadInput');
            const previewInput = document.getElementById('previewExcelFileInput');
            const tokenInput   = document.getElementById('excelTokenPreview');

            const file = fileInput.files[0];

            if (file) {
            // clone file to hidden input
            const dt = new DataTransfer(); dt.items.add(file);
            previewInput.files = dt.files;
            tokenInput.value = ''; // force server to use the new file
            } else if (!tokenInput.value) {
                Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์ก่อนดูตัวอย่าง', confirmButtonText: 'ตกลง' });
                return;
            }

            document.getElementById('previewForm').submit();
        }

        let fpOrder, fpSchedule;

        function syncHiddenDates() {
            const orderHidden = document.getElementById('OrderDate');
            const schedHidden = document.getElementById('ScheduleDate');

            orderHidden.value = (fpOrder?.selectedDates?.[0])
            ? flatpickr.formatDate(fpOrder.selectedDates[0], 'Y-m-d')
            : '';

            schedHidden.value = (fpSchedule?.selectedDates?.[0])
            ? flatpickr.formatDate(fpSchedule.selectedDates[0], 'Y-m-d')
            : '';
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Init pickers (Thai locale, display d-m-Y)
            fpOrder = flatpickr('#OrderDate_display', {
            dateFormat: 'd-m-Y',
            locale: 'th',
            allowInput: false,
            clickOpens: true,
            onChange: syncHiddenDates
            });

            fpSchedule = flatpickr('#ScheduleDate_display', {
            dateFormat: 'd-m-Y',
            locale: 'th',
            allowInput: false,
            clickOpens: true,
            onChange: syncHiddenDates
            });

            // Clear buttons
            document.querySelectorAll('[data-clear]').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('.flatpickr-wrapper');
                const input = wrapper.querySelector('input.flatpickr');
                const instance = input?._flatpickr;
                if (instance) {
                instance.clear();
                syncHiddenDates();
                }
            });
            });

            // Ensure hidden fields are correct on submit
            document.getElementById('editPIForm')?.addEventListener('submit', () => {
            syncHiddenDates();
            });
        });
        function openEditPIModal(btn) {
            const modal = document.getElementById('editPIModal');
            const form  = document.getElementById('editPIForm');

            // Set form action
            form.action = btn.dataset.updateUrl;

            // Prefill inputs
            form.byOrder.value      = btn.dataset.byorder || '';
            form.CustomerPO.value   = btn.dataset.customerpo || '';
            form.OrderDate.value    = btn.dataset.orderdate || '';
            form.ScheduleDate.value = btn.dataset.scheduledate || '';
            

            // Read-only texts
            document.getElementById('piNumberText').textContent   = btn.dataset.pinumber || '-';
            document.getElementById('customerIdText').textContent = btn.dataset.customerid || '-';
            document.getElementById('salespersonNameText').textContent = btn.dataset.salespersonname || '-';

            const order = btn.dataset.orderdate || null;
            const sched = btn.dataset.scheduledate || null;

            if (fpOrder)   fpOrder.setDate(order, true, 'Y-m-d');
            if (fpSchedule) fpSchedule.setDate(sched, true, 'Y-m-d');

            // Sync hidden fields right away
            syncHiddenDates();
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditPIModal() {
            const modal = document.getElementById('editPIModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Click outside to close
        document.getElementById('editPIModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'editPIModal') closeEditPIModal();
        });

        // Esc to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEditPIModal();
        });
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
    <script>
    function confirmDelete(button) {
        event.stopPropagation(); // Prevent card click redirect

        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "การลบนี้ไม่สามารถย้อนกลับได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit the form
                button.closest('form').submit();
            }
        });
    }
    </script>
    @if(session('changes'))
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    const data = @json(session('changes'));
    const items = data.changes || {};
    let html = '';

    const hasChanges = Object.keys(items).length > 0;
    if (hasChanges) {
        for (const [label, diff] of Object.entries(items)) {
        const oldVal = (diff.old ?? '—');
        const newVal = (diff.new ?? '—');
        html += `<div class="text-left mb-1">
                    <b>${label}</b>:
                    <span class="text-gray-500 line-through">${oldVal}</span>
                    &nbsp;→&nbsp;
                    <span class="text-green-600 font-semibold">${newVal}</span>
                </div>`;
        }
    }

    Swal.fire({
        icon: hasChanges ? 'success' : 'info',
        title: data.title || (hasChanges ? 'แก้ไขสำเร็จ' : 'ไม่มีการเปลี่ยนแปลง'),
        html: hasChanges ? html : 'ไม่มีฟิลด์ไหนถูกแก้ไข',
        confirmButtonText: 'ตกลง'
    });
    });
    </script>
    @endif    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    const resumeToken = @json($resume['token'] ?? null);
    const resumeName  = @json($resume['filename'] ?? null);

    if (resumeToken) {
        // open modal
        document.getElementById('excelUploadModal')?.classList.remove('hidden');
        document.getElementById('excelUploadModal')?.classList.add('flex');

        // show filename
        const nameEl = document.getElementById('selectedFileName');
        if (nameEl && resumeName) nameEl.textContent = resumeName;

        // ensure "required" doesn't block submits
        const fileInput = document.getElementById('excelUploadInput');
        fileInput?.removeAttribute('required');
    }
    });
    </script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('[index] resume from server:', @json($resume));
  const uploadInput   = document.getElementById('excelUploadInput');
  const tokenUpload   = document.getElementById('excelTokenUpload');
  const tokenPreview  = document.getElementById('excelTokenPreview');
  const nameEl        = document.getElementById('selectedFileName');

  if (@json(!empty($resume))) {
    console.log('[index] opening modal with resume token:', @json($resume['token'] ?? null));
    document.getElementById('excelUploadModal')?.classList.remove('hidden');
    document.getElementById('excelUploadModal')?.classList.add('flex');
    if (nameEl) nameEl.textContent = @json($resume['filename'] ?? 'ยังไม่ได้เลือกไฟล์');
    uploadInput?.removeAttribute('required');
  }

  uploadInput?.addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    console.log('[index] file changed:', f?.name);
    tokenUpload?.setAttribute('value','');
    tokenPreview?.setAttribute('value','');
    if (nameEl) nameEl.textContent = f ? f.name : 'ยังไม่ได้เลือกไฟล์';
  });

  // When clicking “ดูตัวอย่าง”
  window.submitPreviewForm = function () {
    const previewInput = document.getElementById('previewExcelFileInput');
    const tokenInput   = document.getElementById('excelTokenPreview');
    const file = uploadInput?.files?.[0] || null;

    console.log('[index] submitPreviewForm()', {
      hasNewFile: !!file,
      tokenValue: tokenInput?.value
    });

    if (file) {
      const dt = new DataTransfer(); dt.items.add(file);
      previewInput.files = dt.files;
      tokenInput.value = ''; // force preview to use new file
    } else if (!tokenInput.value) {
      Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์ก่อนดูตัวอย่าง', confirmButtonText: 'ตกลง' });
      return;
    }

    document.getElementById('previewForm').submit();
  };
});
</script>

</x-app-layout>
