<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการ Proforma Invoice ของคุณ
        </h2>
    </x-slot>

    <div class="py-6 px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($pis as $pi)
                <a href="{{ route('proformaInvoice.show', $pi->id) }}" class="block hover:shadow-lg transition duration-300">
                    <div class="bg-white rounded-lg shadow p-5 border border-gray-200">
                        <h3 class="text-lg font-bold text-indigo-600 mb-2">
                            รหัส PI: {{ $pi->PInumber }}
                        </h3>
                        <p><span class="font-semibold">ชื่อลูกค้า:</span> {{ $pi->byOrder }}</p>
                        <p><span class="font-semibold">รหัส PO:</span> {{ $pi->CustomerPO }}</p>
                        <p><span class="font-semibold">รหัสลูกค้า:</span> {{ $pi->CustomerID }}</p>
                        <p><span class="font-semibold">พนักงงานขาย:</span> {{ $pi->Salesperson }}</p>
                        <p><span class="font-semibold">จำนวนสินค้า:</span> {{ $pi->products->count() }} รายการ</p>
                        <p><span class="font-semibold">Schedule Date:</span> 
                            {{ \Carbon\Carbon::parse($pi->ScheduleDate)->format('d-m-Y') }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="text-gray-500 col-span-3">ไม่มีรายการ PI สำหรับคุณ</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
