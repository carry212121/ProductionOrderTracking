<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายละเอียด Proforma Invoice: {{ $pi->PInumber }}
        </h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                <div class="relative bg-white p-6 rounded-lg shadow border">
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
                                    <button type="button"
                                        class="process-btn text-sm px-3 py-1 rounded border border-gray-300 hover:bg-blue-100"
                                        data-target="form-{{ $product->id }}-{{ $eng }}"
                                        @if ($eng === $latestProcessKey) data-active="true" @endif>
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
                                    $isLate = false;
                                    if ($isCurrent && $jc?->ScheduleDate && $jc?->ReceiveDate) {
                                        $schedule = \Carbon\Carbon::parse($jc->ScheduleDate);
                                        $receive = \Carbon\Carbon::parse($jc->ReceiveDate);
                                        $isLate = $receive->gt($schedule);
                                    }

                                    $formClasses = $isCasting ? '' : 'hidden';
                                    $formClasses .= $isCurrent && $isLate ? ' bg-red-100 border-red-400 border-2 rounded-lg p-2' : '';
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
                                        <div>
                                            <label>โรงงาน:</label>
                                            <select name="factory_id" class="border p-1 w-full">
                                                @foreach(\App\Models\Factory::all() as $factory)
                                                    <option value="{{ $factory->id }}" @selected($jc?->factory_id == $factory->id)>
                                                        {{ $factory->FactoryName }}
                                                    </option>
                                                @endforeach
                                            </select>
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
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label>น้ำหนักทั้งหมดก่อน:</label>
                                            <input type="number" name="TotalWeightBefore" value="{{ $jc->TotalWeightBefore ?? '' }}" class="border p-1 w-full">
                                        </div>
                                        <div>
                                            <label>น้ำหนักทั้งหมดหลัง:</label>
                                            <input type="number" name="TotalWeightAfter" value="{{ $jc->TotalWeightAfter ?? '' }}" class="border p-1 w-full">
                                        </div>
                                    </div>

                                    {{-- Fourth Row: AssignDate + ScheduleDate + ReceiveDate --}}
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label>วันสั่ง:</label>
                                            <input type="date" name="AssignDate" value="{{ optional($jc)->AssignDate ? \Carbon\Carbon::parse($jc->AssignDate)->format('Y-m-d') : '' }}" class="border p-1 w-full">
                                        </div>
                                        <div>
                                            <label>วันกำหนดรับ:</label>
                                            <input type="date" name="ScheduleDate" value="{{ optional($jc)->ScheduleDate ? \Carbon\Carbon::parse($jc->ScheduleDate)->format('Y-m-d') : '' }}" class="border p-1 w-full">
                                        </div>
                                        <div>
                                            <label>วันรับ:</label>
                                            <input type="date" name="ReceiveDate" value="{{ optional($jc)->ReceiveDate ? \Carbon\Carbon::parse($jc->ReceiveDate)->format('Y-m-d') : '' }}" class="border p-1 w-full">
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="w-1/3 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">บันทึก</button>
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
                            {{-- <div class="mt-4 flex justify-end mr-12">
                                <button type="submit" class="w-1/3 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">บันทึก</button>
                            </div> --}}
                        </div>
                    </div>
                </div>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('.process-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('div'); // button group
                const allButtons = wrapper.querySelectorAll('.process-btn');
                allButtons.forEach(b => b.classList.remove('bg-blue-500', 'text-white', 'border-blue-500'));

                btn.classList.add('bg-blue-500', 'text-white', 'border-blue-500');

                const productBlock = btn.closest('.grid'); // scope to current product card
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
                const assignDate = new Date(assignDateInput.value);
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
                scheduleDateInput.value = `${yyyy}-${mm}-${dd}`;
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
    </script>
</x-app-layout>
