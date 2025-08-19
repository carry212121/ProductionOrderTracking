@php
    // Late highlight (optional)
    $lateClass = '';
    if ($jc?->ScheduleDate) {
        $schedule = \Carbon\Carbon::parse($jc->ScheduleDate)->startOfDay();
        $referenceDate = $jc?->ReceiveDate
            ? \Carbon\Carbon::parse($jc->ReceiveDate)->startOfDay()
            : \Carbon\Carbon::today();
        $lateDays = $referenceDate->gt($schedule) ? $schedule->diffInDays($referenceDate) : 0;

        if ($lateDays > 15)      $lateClass = 'bg-red-400 border border-red-800';
        elseif ($lateDays > 7)   $lateClass = 'bg-red-100 border border-red-400';
        elseif ($lateDays >= 1)  $lateClass = 'bg-yellow-100 border border-yellow-400';
    }

    $formClasses = trim(($lateClass ? "$lateClass rounded-lg p-2 " : '') . 'border-t pt-2 space-y-2 text-sm ajax-jobcontrol-form');
@endphp

<form method="POST" action="{{ route('jobcontrols.storeOrUpdate') }}"
      class="{{ $formClasses }}"
      data-product-id="{{ $product->id }}"
      data-process="{{ $eng }}"
      id="form-{{ $product->id }}-{{ $eng }}">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <input type="hidden" name="process" value="{{ $eng }}">

    {{-- Row 1: Bill + Factory --}}
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label>รหัสบิล:</label>
            <input type="text" name="Billnumber" value="{{ $jc->Billnumber ?? '' }}" class="border p-1 w-full" {{ $disabledAttr }}>
        </div>

        @php
            $selectedFactoryId    = old('factory_id', $jc?->factory_id);
            $selectedFactory      = $factories->firstWhere('id', $selectedFactoryId);
            $selectedFactoryName  = $selectedFactory->FactoryName ?? '';
            $selectedFactoryNumber= $selectedFactory->FactoryNumber ?? '';
        @endphp

        <div x-data="{ open:false, selected:@js($selectedFactoryId),search:@js($selectedFactoryId ? ($selectedFactoryNumber.' - '.$selectedFactoryName) : '') }" class="relative w-full">
            <label>โรงงาน:</label>
            <input type="hidden" name="factory_id" :value="selected">
            <input type="text" x-model="search" @focus="open = true" @click.away="open = false"
                   placeholder="ค้นหาโรงงาน..." class="w-full border p-1" {{ $disabledAttr }}>
            <ul x-show="open" class="absolute z-50 w-full bg-white border max-h-60 overflow-y-auto mt-1 shadow-md">
                @foreach ($factories as $factory)
                    <li
                        @click="selected = '{{ $factory->id }}'; search = '{{ $factory->FactoryNumber }}-{{ $factory->FactoryName }}'; open = false"
                        x-show="search === '' || '{{ strtolower($factory->FactoryNumber . ' - ' . $factory->FactoryName) }}'.toLowerCase().includes(search.toLowerCase())"
                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer">
                        {{ $factory->FactoryNumber }}-{{ $factory->FactoryName }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Row 2: Qty --}}
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label>จำนวนสั่ง:</label>
            <input type="number" name="QtyOrder" value="{{ $jc->QtyOrder ?? '' }}" class="border p-1 w-full" {{ $disabledAttr }}>
        </div>
        <div>
            <label>จำนวนรับ:</label>
            <input type="number" name="QtyReceive" value="{{ $jc->QtyReceive ?? '' }}" class="border p-1 w-full" {{ $disabledAttr }}>
        </div>
    </div>

    {{-- Row 3: Dates --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="flatpickr-wrapper" data-input>
            <label>วันสั่ง:</label>
            <div class="flex items-center border p-1 w-full">
                <input type="text" name="AssignDate" class="flatpickr w-full"
                       value="{{ $jc?->AssignDate ? \Carbon\Carbon::parse($jc->AssignDate)->format('d-m-Y') : '' }}"
                       data-input readonly {{ $disabledAttr }}>
                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
            </div>
        </div>
        <div class="flatpickr-wrapper" data-input>
            <label>วันกำหนดรับ:</label>
            <div class="flex items-center border p-1 w-full">
                <input type="text" name="ScheduleDate" class="flatpickr w-full"
                       value="{{ $jc?->ScheduleDate ? \Carbon\Carbon::parse($jc->ScheduleDate)->format('d-m-Y') : '' }}"
                       data-input readonly {{ $disabledAttr }}>
                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
            </div>
        </div>
        <div class="flatpickr-wrapper" data-input>
            <label>วันรับ:</label>
            <div class="flex items-center border p-1 w-full">
                <input type="text" name="ReceiveDate" class="flatpickr-max-today w-full"
                       value="{{ $jc?->ReceiveDate ? \Carbon\Carbon::parse($jc->ReceiveDate)->format('d-m-Y') : '' }}"
                       data-input readonly {{ $disabledAttr }}>
                <button type="button" class="text-red-500 px-2" title="Clear Date" data-clear>✕</button>
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-end">
        <button type="submit" class="w-1/3 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600" {{ $disabledAttr }}>
            บันทึก
        </button>
    </div>
</form>
