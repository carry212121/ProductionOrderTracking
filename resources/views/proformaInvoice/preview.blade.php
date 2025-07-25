<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('proformaInvoice.index') }}" class="hover:underline text-blue-600">📄 รายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">ตัวอย่างข้อมูลจากไฟล์ Excel</span>
        </nav>
    </x-slot>

    <div class="p-6 bg-white rounded shadow space-y-10 max-w-7xl mx-auto mt-6">

        {{-- ✅ PI Summary Section --}}
        <div class="space-y-4 border border-green-300 rounded-md p-4 bg-green-50">
            <h2 class="text-lg font-bold text-green-700">📋 ข้อมูล PI</h2>
            @php $piRow = $rows[1] ?? []; @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-800">
                <div><strong>รหัส PI:</strong> <span>{{ $piRow[1] ?? '-' }}</span></div>
                <div><strong>ชื่อลูกค้า:</strong> <span>{{ $piRow[2] ?? '-' }}</span></div>
                <div><strong>รหัสลูกค้า:</strong> <span>{{ $piRow[3] ?? '-' }}</span></div>
                <div><strong>พนักงานขาย:</strong> <span>{{ $piRow[4] ?? '-' }}</span></div>
                <div><strong>วันสั่ง (ว/ด/ป):</strong> <span>{{ is_numeric($piRow[5]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[5])->format('d-m-Y') : $piRow[5] }}</span></div>
                <div><strong>วันกำหนดรับ (ว/ด/ป):</strong> <span>{{ is_numeric($piRow[6]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[6])->format('d-m-Y') : $piRow[6] }}</span></div>
                <div><strong>วันรับ (ว/ด/ป):</strong> <span>{{ is_numeric($piRow[7]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[7])->format('d-m-Y') : $piRow[7] }}</span></div>
                <div><strong>รหัส PO:</strong> <span>{{ $piRow[8] ?? '-' }}</span></div>
                <div class="md:col-span-2"><strong>รายละเอียด PI:</strong> <span>{{ $piRow[12] ?? '-' }}</span></div>
                <div><strong>ค่าส่ง:</strong> <span>{{ $piRow[13] ?? '-' }}</span></div>
                <div><strong>ค่าประกัน:</strong> <span>{{ $piRow[14] ?? '-' }}</span></div>
                <div><strong>เงินฝาก:</strong> <span>{{ $piRow[15] ?? '-' }}</span></div>
            </div>
        </div>

        {{-- ✅ Product Table Section --}}
        <div class="space-y-2 border border-blue-300 rounded-md p-4 bg-blue-50">
            <h2 class="text-lg font-bold text-blue-700">📦 รายการสินค้า</h2>
            <div class="overflow-x-auto">
                <div class="max-h-[240px] overflow-y-auto border border-gray-300 rounded-md">
                    <table class="w-full border-collapse text-sm bg-white">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border px-4 py-2 text-left">รหัสสินค้า</th>
                                <th class="border px-4 py-2 text-left">รายละเอียดสินค้า</th>
                                <th class="border px-4 py-2 text-left">รหัสสินค้าลูกค้า</th>
                                <th class="border px-4 py-2 text-left">น้ำหนัก</th>
                                <th class="border px-4 py-2 text-left">จำนวน</th>
                                <th class="border px-4 py-2 text-left">หน่วยต่อราคา</th>
                                <th class="border px-4 py-2 text-left">จำนวนเงินทั้งหมด</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            @foreach(array_slice($rows, 1) as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="border px-4 py-2">{{ $row[16] ?? '-' }}</td>
                                    <td class="border px-4 py-2">{{ $row[17] ?? '-' }}</td>
                                    <td class="border px-4 py-2">{{ $row[18] ?? '-' }}</td>
                                    <td class="border px-4 py-2">{{ $row[19] ?? '-' }}</td>
                                    <td class="border px-4 py-2">{{ ($row[20] ?? '-') . ' ' . ($row[21] ?? '') }}</td>
                                    <td class="border px-4 py-2">{{ $row[22] ?? '-' }}</td>
                                    <td class="border px-4 py-2">{{ $row[22] * $row[20] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
