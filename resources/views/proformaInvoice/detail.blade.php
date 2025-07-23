<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('proformaInvoice.index') }}" class="hover:underline text-blue-600">รายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าของ {{ $pi->PInumber }}</span>
        </nav>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการสินค้าของ Proforma Invoice: {{ $pi->PInumber }}
        </h2>
        <p>
            วันกำหนดรับ: 
            {{ $pi->ScheduleDate ? \Carbon\Carbon::parse($pi->ScheduleDate)->format('d-m-Y') : '-' }}
        </p>
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

    <div id="no-result-message" class="text-center text-gray-500 mt-6 hidden">
        ไม่พบรหัสสินค้า/สินค้าลูกค้าที่ค้นหา
    </div>
    <div id="no-results" class="text-gray-500 text-center my-4 hidden">
        ไม่พบรายการสินค้าที่ตรงกับตัวกรอง
    </div>

    <div class="px-6 py-4 space-y-6">
        @foreach($pi->products as $index => $product)
                @php
                    $processOrder = ['Casting' => 'หล่อ', 'Stamping' => 'ปั้ม', 'Trimming' => 'แต่ง', 'Polishing' => 'ขัด', 'Setting' => 'ฝัง', 'Plating' => 'ชุบ'];
                    $jobControls = $product->jobControls->keyBy('Process');
                    $hasAnyValue = $jobControls->filter(function ($jc) {
                        return $jc->Billnumber || $jc->factory_id || $jc->QtyOrder || $jc->QtyReceive || $jc->TotalWeightBefore || $jc->TotalWeightAfter || $jc->AssignDate || $jc->ScheduleDate || $jc->ReceiveDate;
                    })->isNotEmpty();

                    $isFinished = $product->Status === 'Finish'; // Assuming you have a `Status` column in product
                    $status = $isFinished ? 'Finish' : ($hasAnyValue ? 'InProgress' : 'Pending');
                        // Get the latest process
                    $latestProcessKey = null;
                    foreach ($processOrder as $eng => $thai) {
                        if (!empty($jobControls[$eng]?->AssignDate)) {
                            $latestProcessKey = $eng;
                        }
                    }
                    $highlightRed = false;
                @endphp
                <div class="relative bg-white p-6 rounded-lg shadow border product-card"
                    data-product-number="{{ $product->ProductNumber }}"
                    data-customer-number="{{ $product->ProductCustomerNumber }}"
                    data-created-at="{{ $product->created_at }}">
                    {{-- Toggle Status Checkbox at top-right --}}
                    {{-- {{ $isFinished ? 'opacity-60 pointer-events-none' : '' }} --}}
                    <form method="POST" action="{{ route('products.toggleStatus', $product->id) }}" class="absolute top-2 right-3"> 
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="{{ $isFinished ? 'InProgress' : 'Finish' }}">
                        <label class="inline-flex items-center text-sm font-medium text-gray-700 gap-1">
                            {{ $status === 'Finish' ? '✅ เสร็จแล้ว' : ($status === 'InProgress' ? '🔄 ดำเนินการ' : '🕓 รอดำเนินการ') }}
                            <input type="checkbox"
                                class="ml-1"
                                {{ $isFinished ? 'checked' : '' }}
                                onchange="confirmToggleStatus(this)"
                            >
                        </label>
                    </form>

                    <div class="grid grid-cols-1 md:grid-cols-[15%_20%_45%_20%] gap-4">

                        {{-- ✅ Column 1: รายละเอียดสินค้า --}}
                        <div>
                            <h3 class="font-bold text-indigo-600 mb-2">รายละเอียดสินค้า</h3>
                            <p>ลำดับที่: {{ $index + 1 }}</p>
                            <p>รหัสสินค้า: {{ $product->ProductNumber }}</p>
                            <p>รหัสสินค้าลูกค้า: {{ $product->ProductCustomerNumber }}</p>
                            <p>จำนวน: {{ $product->Quantity }}</p>
                            <p>น้ำหนัก: {{ $product->Weight }}</p>
                        </div>

                        {{-- ✅ Column 2: รูปภาพสินค้า --}}
                        <div>
                            <h3 class="font-bold text-indigo-600 mb-2">รูปภาพสินค้า</h3>
                            @php
                                $img = $product->Image ?? 'images/default-product.jpg';
                            @endphp
                            <img src="{{ asset($img) }}" alt="Product Image" class="w-full h-70 object-contain border">
                        </div>
                        {{-- ✅ Column 3: กระบวนการ --}}
                        <div class="{{ $highlightRed ? 'bg-red-100 border-red-400 border-2 rounded-lg p-2' : '' }}">
                            <h3 class="font-bold text-indigo-600 mb-2">กระบวนการ</h3>

                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach ($processOrder as $eng => $thai)
                                    @php
                                        $jc = $jobControls[$eng] ?? null;
                                        $lateDays = null;
                                        $lateClass = '';

                                        if ($jc?->ScheduleDate) {
                                            $schedule = \Carbon\Carbon::parse($jc->ScheduleDate)->startOfDay();
                                            $referenceDate = $jc->ReceiveDate
                                                ? \Carbon\Carbon::parse($jc->ReceiveDate)->startOfDay()
                                                : \Carbon\Carbon::today();

                                            $lateDays = $referenceDate->gt($schedule) ? $schedule->diffInDays($referenceDate) : 0;

                                            if ($lateDays > 15) {
                                                $lateClass = 'bg-red-400 text-white border border-red-800';
                                            } elseif ($lateDays > 7) {
                                                $lateClass = 'bg-red-200 border border-red-500';
                                            } elseif ($lateDays >= 1) {
                                                $lateClass = 'bg-yellow-100 border border-yellow-400';
                                            }
                                        }

                                        $isActive = $eng === $latestProcessKey;
                                        $buttonClasses = 'process-btn text-sm px-3 py-1 rounded border transition';

                                        if ($isActive) {
                                            $buttonClasses .= ' bg-blue-500 text-white border-blue-700';
                                        } else {
                                            $buttonClasses .= ' ' . $lateClass;
                                        }
                                    @endphp

                                    <button type="button"
                                        class="{{ $buttonClasses }}"
                                        data-target="form-{{ $product->id }}-{{ $eng }}"
                                        data-late-class="{{ $lateClass }}"
                                        @if ($isActive) data-active="true" @endif>
                                        {{ $thai }}
                                    </button>
                                @endforeach


                            </div>

                            @foreach ($processOrder as $eng => $thai)
                                @php
                                    $jc = $jobControls[$eng] ?? null;
                                    $isCasting = $eng === 'Casting';

                                    // Only for current/latest process, check if it's late
                                    $isCurrent = $eng === $latestProcessKey;
                                    $lateDays = null;
                                    $lateClass = '';

                                    if ($jc?->ScheduleDate) {
                                        $schedule = \Carbon\Carbon::parse($jc->ScheduleDate)->startOfDay();

                                        if ($jc->ReceiveDate) {
                                            $referenceDate = \Carbon\Carbon::parse($jc->ReceiveDate)->startOfDay();
                                        } else {
                                            $referenceDate = \Carbon\Carbon::today();
                                        }

                                        $lateDays = $referenceDate->gt($schedule) ? $schedule->diffInDays($referenceDate) : 0;

                                        echo "<script>console.log('📦 Product ID: {$product->id} | Process: {$eng} | Schedule: {$schedule} | RefDate: {$referenceDate} | Late Days: {$lateDays}');</script>";

                                        if ($lateDays > 15) {
                                            $lateClass = 'bg-red-400 border border-red-800';
                                        } elseif ($lateDays > 7) {
                                            $lateClass = 'bg-red-100 border border-red-400';
                                        } elseif ($lateDays >= 1) {
                                            $lateClass = 'bg-yellow-100 border border-yellow-400';
                                        }
                                    }

                                    $formClasses = $isCasting ? '' : 'hidden';
                                    $formClasses .= $lateClass ? " $lateClass rounded-lg p-2" : '';
                                @endphp
                                <form method="POST" action="{{ route('jobcontrols.storeOrUpdate') }}" id="form-{{ $product->id }}-{{ $eng }}"
                                    class="{{ $formClasses }} border-t pt-2 space-y-2 text-sm">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="process" value="{{ $eng }}">
                                    {{-- First Row: Billnumber + Factory --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label>รหัสบิล:</label>
                                            <input type="text" name="Billnumber" value="{{ $jc->Billnumber ?? '' }}" class="border p-1 w-full" >
                                        </div>
                                        @php
                                            $factories = \App\Models\Factory::all();
                                            $selectedFactoryId = old('factory_id', $jc?->factory_id);
                                            $selectedFactory = $factories->firstWhere('id', $selectedFactoryId);
                                            $selectedFactoryName = $selectedFactory ? $selectedFactory->FactoryName : '';
                                        @endphp

                                        <div 
                                            x-data="{ 
                                                open: false, 
                                                search: '{{ $selectedFactoryName }}', 
                                                selected: '{{ $selectedFactoryId }}' 
                                            }" 
                                            class="relative w-full"
                                        >
                                            <label>โรงงาน:</label>
                                            <input type="hidden" name="factory_id" :value="selected">

                                            <input
                                                type="text"
                                                x-model="search"
                                                @focus="open = true"
                                                @click.away="open = false"
                                                placeholder="ค้นหาโรงงาน..."
                                                class="w-full border p-1"
                                            >

                                            <ul x-show="open" class="absolute z-50 w-full bg-white border max-h-60 overflow-y-auto mt-1 shadow-md">
                                                @foreach ($factories as $factory)
                                                    <li
                                                        @click="selected = '{{ $factory->id }}'; search = '{{ $factory->FactoryName }}'; open = false"
                                                        x-show="search === '' || '{{ strtolower($factory->FactoryName) }}'.includes(search.toLowerCase())"
                                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                                                    >
                                                        {{ $factory->FactoryName }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>

                                    {{-- Second Row: QtyOrder + QtyReceive --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label>จำนวนสั่ง:</label>
                                            <input type="number" name="QtyOrder" value="{{ $jc->QtyOrder ?? '' }}" class="border p-1 w-full">
                                        </div>
                                        <div>
                                            <label>จำนวนรับ:</label>
                                            <input type="number" name="QtyReceive" value="{{ $jc->QtyReceive ?? '' }}" class="border p-1 w-full">
                                        </div>
                                    </div>

                                    {{-- Third Row: TotalWeightBefore + TotalWeightAfter --}}
                                    {{-- <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label>น้ำหนักทั้งหมดก่อน:</label>
                                            <input type="number" name="TotalWeightBefore" value="{{ $jc->TotalWeightBefore ?? '' }}" class="border p-1 w-full">
                                        </div>
                                        <div>
                                            <label>น้ำหนักทั้งหมดหลัง:</label>
                                            <input type="number" name="TotalWeightAfter" value="{{ $jc->TotalWeightAfter ?? '' }}" class="border p-1 w-full">
                                        </div>
                                    </div> --}}

                                    {{-- Fourth Row: AssignDate + ScheduleDate + ReceiveDate --}}
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="flatpickr-wrapper" data-input>
                                            <label>วันสั่ง:</label>
                                            <div class="flex items-center border p-1 w-full">
                                                <input type="text"
                                                    name="AssignDate"
                                                    class="flatpickr w-full"
                                                    value="{{ optional($jc)->AssignDate ? \Carbon\Carbon::parse($jc->AssignDate)->format('d-m-Y') : '' }}"
                                                    data-input readonly>
                                                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
                                            </div>
                                        </div>

                                        <div class="flatpickr-wrapper" data-input>
                                            <label>วันกำหนดรับ:</label>
                                            <div class="flex items-center border p-1 w-full">
                                                <input type="text"
                                                    name="ScheduleDate"
                                                    class="flatpickr w-full"
                                                    value="{{ optional($jc)->ScheduleDate ? \Carbon\Carbon::parse($jc->ScheduleDate)->format('d-m-Y') : '' }}"
                                                    data-input readonly>
                                                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
                                            </div>
                                        </div>
                                        <div class="flatpickr-wrapper" data-input>
                                            <label>วันรับ:</label>
                                            <div class="flex items-center border p-1 w-full">
                                                <input type="text"
                                                    name="ReceiveDate"
                                                    class="flatpickr-max-today w-full"
                                                    value="{{ optional($jc)->ReceiveDate ? \Carbon\Carbon::parse($jc->ReceiveDate)->format('d-m-Y') : '' }}"
                                                    data-input
                                                    readonly>
                                                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
                                            </div>
                                        </div>


                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="w-1/3 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">บันทึก</button>
                                    </div>
                                </form>

                            @endforeach
                        </div>


                        {{-- ✅ Column 4: คำอธิบายสินค้า --}}
                        <div class="flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-indigo-600 mb-2">รายละเอียดเพิ่มเติม</h3>
                                <p>{{ $product->Description ?? 'ไม่มีรายละเอียด' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('.process-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('div'); // group of buttons
                const allButtons = wrapper.querySelectorAll('.process-btn');

                allButtons.forEach(b => {
                    b.classList.remove('bg-blue-500', 'text-white', 'border-blue-500', 'border-blue-700');

                    // Restore original lateness class
                    const lateClass = b.dataset.lateClass || '';
                    const lateClasses = lateClass.split(' ').filter(Boolean);
                    lateClasses.forEach(cls => b.classList.add(cls));
                });

                // Apply selected (blue) highlight
                btn.classList.remove('bg-red-400', 'bg-red-200', 'bg-yellow-100', 'text-white', 'border-red-800', 'border-red-500', 'border-yellow-400');
                btn.classList.add('bg-blue-500', 'text-white', 'border-blue-500');

                const productBlock = btn.closest('.grid');
                const allForms = productBlock.querySelectorAll('form[id^="form-"]');
                allForms.forEach(f => f.classList.add('hidden'));

                const targetId = btn.dataset.target;
                document.getElementById(targetId).classList.remove('hidden');
            });
        });
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

                const scheduleDate = new Date(assignDate);
                scheduleDate.setDate(assignDate.getDate() + daysToAdd);

                // Format to YYYY-MM-DD
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

    </script>
</x-app-layout>
