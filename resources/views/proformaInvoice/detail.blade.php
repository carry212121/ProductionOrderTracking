<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('proformaInvoice.index') }}" class="hover:underline text-blue-600">รายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าของ {{ $pi->PInumber }}</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/customParseFormat.js"></script>
    <script>
        dayjs.extend(dayjs_plugin_customParseFormat);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @php
        $processOrder = ['Casting' => 'หล่อ', 'Stamping' => 'ปั้ม', 'Trimming' => 'แต่ง', 'Polishing' => 'ขัด', 'Setting' => 'ฝัง', 'Plating' => 'ชุบ'];
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 px-6 mt-4 items-start">
        <div><h2 class="text-xl font-semibold text-gray-800">รายการสินค้าของ Proforma Invoice: {{ $pi->PInumber }}</h2></div>
        <div class="space-y-1 text-gray-700">
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
        <div class="col-span-1 flex items-center justify-end gap-2 flex-wrap">
            <x-filter toggleId="filterToggleBtn" panelId="filterPanel">
                <button class="filter-option w-full text-left hover:bg-gray-200 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                <button class="filter-option w-full text-left hover:bg-yellow-100 px-2 py-1" data-filter="yellow">🟡 ช้า 1–7 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="red">🔴 ช้า 8–14 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-400 px-2 py-1" data-filter="darkred">🟥 ช้าเกิน 15 วัน</button>
            </x-filter>
            <x-search-bar id="product-search" placeholder="ค้นหารหัสสินค้า/สินค้าลูกค้า..." class="w-64" />
        </div>
    </div>

    <div id="no-result-message" class="text-center text-gray-500 mt-6 hidden">ไม่พบรหัสสินค้า/สินค้าลูกค้าที่ค้นหา</div>
    <div id="no-results" class="text-gray-500 text-center my-4 hidden">ไม่พบรายการสินค้าที่ตรงกับตัวกรอง</div>

    {{-- List --}}
    <div class="px-6 py-4 space-y-6">
        @foreach($products as $index => $product)
            @php
                $rowNumber = $products->firstItem() + $index;
                $jobControls = $product->jobControls->keyBy('Process');
                $hasAnyValue = $jobControls->filter(fn($jc) =>
                    $jc->Billnumber || $jc->factory_id || $jc->QtyOrder || $jc->QtyReceive ||
                    $jc->TotalWeightBefore || $jc->TotalWeightAfter ||
                    $jc->AssignDate || $jc->ScheduleDate || $jc->ReceiveDate
                )->isNotEmpty();

                $isFinished = $product->Status === 'Finish';
                $status     = $isFinished ? 'Finish' : ($hasAnyValue ? 'InProgress' : 'Pending');

                $latestProcessKey = null;
                foreach ($processOrder as $eng => $thai) {
                    if (!empty($jobControls[$eng]?->AssignDate)) {
                        $latestProcessKey = $eng;
                    }
                }
                $activeEng    = $latestProcessKey ?? 'Casting';
                $activeJc     = $jobControls[$activeEng] ?? null;
                $disabledAttr = $isFinished ? 'disabled' : '';
            @endphp

            <div class="relative bg-white p-6 rounded-lg shadow border product-card"
                 data-product-number="{{ $product->ProductNumber }}"
                 data-customer-number="{{ $product->ProductCustomerNumber }}"
                 data-created-at="{{ $product->created_at }}">

                {{-- Toggle Finish --}}
                <form method="POST" action="{{ route('products.toggleStatus', $product->id) }}" class="absolute top-2 right-3 {{ $isFinished ? 'opacity-60 pointer-events-none' : '' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="{{ $isFinished ? 'InProgress' : 'Finish' }}">
                    <label class="inline-flex items-center text-sm font-medium text-gray-700 gap-1">
                        {{ $status === 'Finish' ? '✅ เสร็จแล้ว' : ($status === 'InProgress' ? '🔄 ดำเนินการ' : '🕓 รอดำเนินการ') }}
                        <input type="checkbox" class="ml-1" {{ $isFinished ? 'checked' : '' }} onchange="confirmToggleStatus(this)">
                    </label>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-[15%_20%_45%_20%] gap-4">
                    {{-- Col 1: product --}}
                    <div>
                        <h3 class="font-bold text-indigo-600 mb-2">รายละเอียดสินค้า</h3>
                        <p>ลำดับที่: {{ $rowNumber }}</p>
                        <p>รหัสสินค้า: {{ $product->ProductNumber }}</p>
                        <p>รหัสสินค้าลูกค้า: {{ $product->ProductCustomerNumber }}</p>
                        <p>จำนวน: {{ $product->Quantity }}</p>
                        <p>น้ำหนัก: {{ $product->Weight }}</p>
                    </div>

                    {{-- Col 2: image --}}
                    <div>
                        <h3 class="font-bold text-indigo-600 mb-2">รูปภาพสินค้า</h3>
                        @php
                            $remote = 'http://192.168.0.100/picture/' . rawurlencode($product->ProductNumber) . '.JPG';
                            $default = asset('images/default-product.jpg');
                        @endphp
                        <img
                            src="{{ $remote }}"
                            onerror="this.onerror=null; this.src='{{ $default }}';"
                            alt="Product Image"
                            class="max-h-full max-w-full object-scale-down"
                            loading="lazy"
                        />
                    </div>

                    {{-- Col 3: processes (buttons + mount) --}}
                    <div>
                        <h3 class="font-bold text-indigo-600 mb-2">กระบวนการ</h3>
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach ($processOrder as $eng => $thai)
                                @php
                                    $jc = $jobControls[$eng] ?? null;
                                    $lateClass = '';
                                    if ($jc?->ScheduleDate) {
                                        $schedule = \Carbon\Carbon::parse($jc->ScheduleDate)->startOfDay();
                                        $referenceDate = $jc->ReceiveDate
                                            ? \Carbon\Carbon::parse($jc->ReceiveDate)->startOfDay()
                                            : \Carbon\Carbon::today();
                                        $lateDays = $referenceDate->gt($schedule) ? $schedule->diffInDays($referenceDate) : 0;
                                        if ($lateDays > 15)      $lateClass = 'bg-red-400 text-white border border-red-800';
                                        elseif ($lateDays > 7)   $lateClass = 'bg-red-200 border border-red-500';
                                        elseif ($lateDays >= 1)  $lateClass = 'bg-yellow-100 border border-yellow-400';
                                    }
                                    $isActive = $eng === $activeEng;
                                    $btnClasses = 'process-btn text-sm px-3 py-1 rounded border transition ' . ($isActive ? 'bg-blue-500 text-white border-blue-700' : $lateClass);
                                @endphp
                                <button type="button"
                                        class="{{ $btnClasses }}"
                                        data-product-id="{{ $product->id }}"
                                        data-process="{{ $eng }}"
                                        data-target="form-{{ $product->id }}-{{ $eng }}"
                                        data-late-class="{{ $lateClass }}"
                                        @if ($isActive) data-active="true" @endif>
                                    {{ $thai }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Mount: only render the *active* form initially --}}
                        <div id="process-form-{{ $product->id }}" data-product-id="{{ $product->id }}">
                            <div class="process-pane" data-pane="{{ $activeEng }}">
                                @include('proformaInvoice._process_form', [
                                    'product'      => $product,
                                    'eng'          => $activeEng,
                                    'jc'           => $activeJc,
                                    'factories'    => $factories,
                                    'disabledAttr' => $disabledAttr,
                                ])
                            </div>
                        </div>
                    </div>

                    {{-- Col 4: desc --}}
                    <div class="flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-indigo-600 mb-2">รายละเอียดเพิ่มเติม</h3>
                            <div class="w-64 h-64 overflow-y-auto border p-2 rounded">
                                {{ $product->Description ?? 'ไม่มีรายละเอียด' }}
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="px-6 pb-8">
    {{ $products->withQueryString()->links() }}
    </div>

    <script>
        const formCache = new Map(); // `${productId}:${process}` -> HTML

        function initFlatpickrInside(el) {
            el.querySelectorAll('.flatpickr-wrapper').forEach(wrapper => {
                flatpickr(wrapper, { dateFormat: "d-m-Y", allowInput: false, wrap: true, clearBtn: true });
            });
            el.querySelectorAll('.flatpickr-max-today').forEach(inp => {
                flatpickr(inp, { dateFormat: "d-m-Y", allowInput: false, maxDate: "today", clearBtn: true });
            });
        }
        function bindAutoSchedule(scope=document) {
            scope.querySelectorAll('form[id^="form-"]').forEach(form => {
            if (form.dataset.autoBound === "1") return; // prevent double bindings
            form.dataset.autoBound = "1";

            const assign = form.querySelector('input[name="AssignDate"]');
            const sched  = form.querySelector('input[name="ScheduleDate"]');
            const proc   = form.querySelector('input[name="process"]');
            if (!assign || !sched || !proc) return;

            const recompute = () => {
                const [d, m, y] = (assign.value || '').split('-');
                const assignDate = new Date(`${y}-${m}-${d}`);
                if (!assignDate.getTime()) return;

                // per-process default days
                let daysToAdd = 7;
                const p = proc.value;
                if (p === 'Casting') daysToAdd = 10;
                else if (p === 'Stamping') daysToAdd = 14;

                // holiday ranges (Thai New Year & New Year cross-year safe)
                const holidayRanges = [
                { start: { day:12, month:4 }, end: { day:17, month:4 } },
                { start: { day:29, month:12 }, end: { day:2,  month:1 } },
                ];
                const isHoliday = (date) => holidayRanges.some(r => {
                const s = new Date(date.getFullYear(), r.start.month-1, r.start.day);
                const e = new Date(date.getFullYear() + (r.end.month < r.start.month ? 1 : 0), r.end.month-1, r.end.day);
                return date >= s && date <= e;
                });

                let cur = new Date(assignDate), added = 0;
                while (added < daysToAdd) {
                cur.setDate(cur.getDate() + 1);
                if (!isHoliday(cur)) added++;
                }

                const yyyy = cur.getFullYear();
                const mm = String(cur.getMonth()+1).padStart(2,'0');
                const dd = String(cur.getDate()).padStart(2,'0');
                sched.value = `${dd}-${mm}-${yyyy}`;
            };

            // Bind native events
            assign.addEventListener('change', recompute);
            assign.addEventListener('input', recompute);

            // If flatpickr is attached, hook into it as well
            if (assign._flatpickr) {
                assign._flatpickr.config.onChange.push(recompute);
                assign._flatpickr.config.onClose.push(recompute);
            }
            });
        }
        async function loadProcessForm(productId, process) {
            const key = `${productId}:${process}`;
            if (formCache.has(key)) return formCache.get(key);

            const url = `{{ route('products.process-form', ':id') }}`
                .replace(':id', productId) + `?process=${encodeURIComponent(process)}`;

            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!res.ok) throw new Error(`โหลดฟอร์มไม่สำเร็จ (${res.status})`);
            const html = await res.text();
            formCache.set(key, html);
            return html;
        }
        document.querySelectorAll('.process-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const productId = btn.dataset.productId;
                const process   = btn.dataset.process;
                const group     = btn.closest('.mb-3') || btn.parentElement;

                // Reset all buttons in the same product's group
                group.querySelectorAll('.process-btn').forEach(b => {
                    b.dataset.active = "false";
                    b.classList.remove(
                        'bg-blue-500', 'text-white', 'border-blue-500', 'border-blue-700',
                        'bg-red-400', 'bg-red-200', 'bg-yellow-100',
                        'border-red-800', 'border-red-500', 'border-yellow-400'
                    );
                    // restore lateness class (not for active one)
                    const lateClass = (b.dataset.lateClass || '').split(' ').filter(Boolean);
                    lateClass.forEach(cls => b.classList.add(cls));
                });

                // Mark clicked button as active + blue
                btn.dataset.active = "true";
                btn.classList.remove(
                    'bg-red-400', 'bg-red-200', 'bg-yellow-100',
                    'border-red-800', 'border-red-500', 'border-yellow-400'
                );
                btn.classList.add('bg-blue-500', 'text-white', 'border-blue-500');

                try {
                    await ensureProcessPane(productId, process);
                    showProcessPane(productId, process);
                } catch (e) {
                    console.error(e);
                    const mount = document.getElementById(`process-form-${productId}`);
                    if (mount) {
                        let err = mount.querySelector('.process-load-error');
                        if (!err) {
                            err = document.createElement('div');
                            err.className = 'process-load-error py-2 text-red-600';
                            mount.appendChild(err);
                        }
                        err.textContent = 'โหลดไม่สำเร็จ ลองอีกครั้ง';
                    }
                }
            });
        });
        window.productIdToNumber = @json(\App\Models\Product::pluck('ProductNumber', 'id'));
        window.processNameMap    = @json($processOrder);
        // AJAX save wiring
        function wireAjaxSave(scope=document) {
        scope.querySelectorAll('.ajax-jobcontrol-form').forEach(form => {
            if (form.dataset.bound === "1") return;   // prevent double binding
            form.dataset.bound = "1";

            form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const action = form.getAttribute('action');
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'กำลังบันทึก...';

            try {
                const response = await fetch(action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: formData
                });

                if (!response.ok) throw new Error(await response.text());

                // Build the detailed success modal here
                const productId = formData.get('product_id');
                const productNumber = (window.productIdToNumber || {})[productId] || '-';
                const process = formData.get('process');
                const processThai = processNameMap[process] || process;

                Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                html: `
                    <div class="text-left leading-relaxed">
                    <strong>รหัสสินค้า:</strong> ${productNumber}<br>
                    <strong>กระบวนการ:</strong> ${processThai}<br>
                    ข้อมูลของ JobControl ได้ถูกบันทึกแล้ว
                    </div>
                `,
                confirmButtonColor: '#3085d6'
                });

                // ✅ Remove all active flags for this product
                document.querySelectorAll(`[data-product-id="${productId}"] .process-btn`).forEach(btn => {
                    btn.dataset.active = "false";
                });

                // ✅ Mark the current saved process as active
                const currentButton = document.querySelector(`[data-target="form-${productId}-${process}"]`);
                if (currentButton) {
                    currentButton.dataset.active = "true";
                    console.log(`✅ Marked as active: Process ${process} for Product ${productId}`);
                } else {
                    console.warn(`⚠️ Could not find button for process ${process} of Product ${productId}`);
                }
                updateLateStatus(productId, process);

            } catch (err) {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', html: `<pre style="text-align:left;">${err.message}</pre>`, width: 600 });
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'บันทึก';
            }
            });
        });
        }

        async function fetchProcessHTML(productId, process) {
            const key = `${productId}:${process}`;
            if (formCache.has(key)) return formCache.get(key);

            const url = `{{ route('products.process-form', ':id') }}`
            .replace(':id', productId) + `?process=${encodeURIComponent(process)}`;
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!res.ok) throw new Error(`โหลดฟอร์มไม่สำเร็จ (${res.status})`);
            const html = await res.text();
            formCache.set(key, html);
            return html;
        }

        async function ensureProcessPane(productId, process) {
            const mount = document.getElementById(`process-form-${productId}`);
            // already mounted? just return it
            let pane = mount.querySelector(`.process-pane[data-pane="${process}"]`);
            if (pane) return pane;

            // fetch and append new pane (do NOT replace mount.innerHTML)
            const html = await fetchProcessHTML(productId, process);
            pane = document.createElement('div');
            pane.className = 'process-pane';
            pane.dataset.pane = process;
            pane.style.display = 'none';
            pane.innerHTML = html;
            mount.appendChild(pane);

            // init only the newly added pane
            initFlatpickrInside(pane);
            bindAutoSchedule(pane);
            wireAjaxSave(pane);

            return pane;
        }

        function showProcessPane(productId, process) {
            const mount = document.getElementById(`process-form-${productId}`);
            mount.querySelectorAll('.process-pane').forEach(p => {
            p.style.display = (p.dataset.pane === process) ? 'block' : 'none';
            });
        }

        // First paint: init for server-rendered active forms only
        wireAjaxSave(document);
        initFlatpickrInside(document);
        bindAutoSchedule(document);

        document.querySelectorAll('.mb-3.flex').forEach(wrapper => {
            const activeBtn = wrapper.querySelector('.process-btn[data-active="true"]');
            const btn = activeBtn || wrapper.querySelector('.process-btn');

            if (btn) {
                btn.classList.add('bg-blue-500', 'text-white', 'border-blue-500');

                const productBlock = btn.closest('.grid');
                const allForms = productBlock.querySelectorAll('form[id^="form-"]');
                allForms.forEach(f => f.classList.add('hidden'));

                const targetId = btn.dataset.target;
                document.getElementById(targetId)?.classList.remove('hidden');
            }
        });

        document.querySelectorAll('form[id^="form-"]').forEach(form => {
            const assignDateInput = form.querySelector('input[name="AssignDate"]');
            const scheduleDateInput = form.querySelector('input[name="ScheduleDate"]');
            const processInput = form.querySelector('input[name="process"]');

            if (!assignDateInput || !scheduleDateInput || !processInput) return;

            assignDateInput.addEventListener('change', () => {
                const [day, month, year] = assignDateInput.value.split('-');
                const assignDate = new Date(`${year}-${month}-${day}`);

                if (!assignDate.getTime()) return;

                let daysToAdd = 7;
                const process = processInput.value;

                if (process === 'Casting') daysToAdd = 10;
                else if (process === 'Stamping') daysToAdd = 14;
                else if (['Trimming', 'Polishing', 'Setting', 'Plating'].includes(process)) daysToAdd = 7;

                // Define holiday ranges (year-agnostic ranges for April and December)
                const holidayRanges = [
                    { start: { day: 12, month: 4 }, end: { day: 17, month: 4 } }, // 12-17 April
                    { start: { day: 29, month: 12 }, end: { day: 2, month: 1 } }, // 29 Dec - 2 Jan
                ];

                // Function to check if a date is within any holiday range
                function isHoliday(date) {
                    return holidayRanges.some(range => {
                        const start = new Date(date.getFullYear(), range.start.month - 1, range.start.day);
                        let end = new Date(date.getFullYear(), range.end.month - 1, range.end.day);

                        // If crossing year boundary (e.g. Dec → Jan), adjust end year
                        if (range.end.month < range.start.month) {
                            end.setFullYear(end.getFullYear() + 1);
                        }

                        return date >= start && date <= end;
                    });
                }

                // Count non-holiday days
                let scheduleDate = new Date(assignDate);
                let addedDays = 0;

                while (addedDays < daysToAdd) {
                    scheduleDate.setDate(scheduleDate.getDate() + 1);
                    if (!isHoliday(scheduleDate)) {
                        addedDays++;
                    }
                }

                // Format as dd-mm-yyyy
                const yyyy = scheduleDate.getFullYear();
                const mm = String(scheduleDate.getMonth() + 1).padStart(2, '0');
                const dd = String(scheduleDate.getDate()).padStart(2, '0');
                scheduleDateInput.value = `${dd}-${mm}-${yyyy}`;
            });
        });
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '{{ session('success') }}',
                timer: 2000,
                showConfirmButton: false
            });
        @elseif (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '{{ session('error') }}',
            });
        @endif
        function confirmToggleStatus(checkbox) {
            const form = checkbox.closest('form');
            const isChecked = checkbox.checked;
            const statusLabel = isChecked ? 'เสร็จแล้ว' : 'กำลังดำเนินการ';

            if (isChecked) {
                // Confirm before marking as Finish
                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: "คุณต้องการเปลี่ยนสถานะเป็น 'เสร็จแล้ว' หรือไม่? หลังจากยืนยันแล้วจะไม่สามารถเปลี่ยนกลับได้",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#aaa',
                    confirmButtonText: 'ยืนยัน',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    } else {
                        checkbox.checked = false; // Reset checkbox
                    }
                });
            } else {
                // Allow switching back without confirm
                form.submit();
            }
        }
        // 🗓️ For AssignDate & ScheduleDate — clearable with no maxDate
        flatpickr(".flatpickr-wrapper:not(.flatpickr-max-today-wrapper)", {
            dateFormat: "d-m-Y",
            allowInput: false,
            wrap: true,
            clearBtn: true
        });

        // 🛑 For ReceiveDate — clearable with maxDate: today
        flatpickr(".flatpickr-max-today", {
            dateFormat: "d-m-Y",
            allowInput: false,
            maxDate: "today",
            clearBtn: true
        });

        function updateLateStatus(productId, processKey = null) {
            const baseSelector = `[data-product-id="${productId}"]`;

            const targetProcesses = processKey
                ? [processKey]
                : Array.from(document.querySelectorAll(`${baseSelector} .process-btn`))
                        .map(btn => btn.dataset.target.split('-').pop());

            targetProcesses.forEach(process => {
                const button = document.querySelector(`#form-${productId}-${process}`)?.closest('.grid')?.querySelector(`[data-target="form-${productId}-${process}"]`);
                const form = document.querySelector(`#form-${productId}-${process}`);
                if (!form || !button) return;

                const scheduleInput = form.querySelector('[name="ScheduleDate"]');
                const receiveInput = form.querySelector('[name="ReceiveDate"]');

                const schedule = scheduleInput?.value ? dayjs(scheduleInput.value, 'DD-MM-YYYY') : null;
                const receive = receiveInput?.value ? dayjs(receiveInput.value, 'DD-MM-YYYY') : dayjs();

                let lateDays = 0;
                if (schedule && receive && receive.isAfter(schedule)) {
                    lateDays = receive.diff(schedule, 'day');
                }

                // Reset classes
                button.classList.remove(
                    'bg-blue-500', 'text-white', 'border-blue-500',
                    'bg-red-400', 'bg-red-200', 'bg-yellow-100',
                    'border-red-800', 'border-red-500', 'border-yellow-400'
                );
                form.classList.remove(
                    'bg-red-400', 'bg-red-200', 'bg-yellow-100',
                    'border-red-800', 'border-red-500', 'border-yellow-400'
                );

                // Reapply blue for active process
                const isActive = button.dataset.active === "true";
                if (isActive) {
                    button.classList.add('bg-blue-500', 'text-white', 'border-blue-500');
                    console.log(`🔵 BLUE BUTTON → Product ${productId} | Process ${process} marked as ACTIVE`);
                } else {
                    console.log(`⚪ Not active: Product ${productId} | Process ${process}`);
                }

                // Determine lateness style for form (but NOT button if active)
                let colorLabel = '✅ On time';
                let lateClass = '';

                if (lateDays > 15) {
                    if (!isActive) button.classList.add('bg-red-400', 'text-white', 'border-red-800');
                    form.classList.add('bg-red-400', 'border-red-800');
                    form.classList.add('rounded-lg', 'p-2');
                    lateClass = 'bg-red-400 text-white border border-red-800';
                    colorLabel = '🔴 Very Late';
                } else if (lateDays > 7) {
                    if (!isActive) button.classList.add('bg-red-200', 'border-red-500');
                    form.classList.add('bg-red-200', 'border-red-500');
                    form.classList.add('rounded-lg', 'p-2');
                    lateClass = 'bg-red-200 border border-red-500';
                    colorLabel = '🟥 Late';
                } else if (lateDays >= 1) {
                    if (!isActive) button.classList.add('bg-yellow-100', 'border-yellow-400');
                    form.classList.add('bg-yellow-100', 'border-yellow-400');
                    form.classList.add('rounded-lg', 'p-2');
                    lateClass = 'bg-yellow-100 border border-yellow-400';
                    colorLabel = '🟨 Slightly Late';
                }

                // Update attribute
                button.setAttribute('data-late-class', lateClass);

                console.log(`📦 Product ${productId} | Process ${process} | Late Days: ${lateDays} | Button Color: ${colorLabel}`);
            });
        }

    </script>
</x-app-layout>
