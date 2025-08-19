<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('proformaInvoice.index', ['excel_token' => $excelToken]) }}" class="hover:underline text-blue-600">📄 รายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">ตัวอย่างข้อมูลจากไฟล์ Excel</span>
        </nav>
    </x-slot>

    <div class="p-6 bg-white rounded shadow space-y-10 max-w-7xl mx-auto mt-6">

        {{-- ✅ PI Summary Section --}}
        <div class="space-y-4 border border-green-300 rounded-md p-4 bg-green-50">
            <h2 class="text-lg font-bold text-green-700">📋 ข้อมูล PI</h2>
            @php $piRow = $rows[1] ?? []; @endphp
            @php
                $totalAmount = 0;
                foreach(array_slice($rows, 1) as $row) {
                    $qty = is_numeric($row[20] ?? null) ? $row[20] : 0;
                    $unitPrice = is_numeric($row[22] ?? null) ? $row[22] : 0;
                    $totalAmount += $qty * $unitPrice;
                }
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-800">
                <div><strong>รหัส PI:</strong> <span class="font-bold text-blue-600">{{ $piRow[1] ?? '-' }}</span></div>
                <div><strong>ชื่อลูกค้า:</strong> <span class="font-bold text-blue-600">{{ $piRow[2] ?? '-' }}</span></div>
                <div><strong>รหัสลูกค้า:</strong> <span class="font-bold text-blue-600">{{ $piRow[3] ?? '-' }}</span></div>
                <div><strong>พนักงานขาย:</strong> <span class="font-bold text-blue-600">{{ $piRow[4] ?? '-' }}</span></div>
                <div><strong>วันสั่ง (ว/ด/ป):</strong> <span class="font-bold text-blue-600">{{ is_numeric($piRow[5]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[5])->format('d-m-Y') : $piRow[5] }}</span></div>
                <div><strong>วันกำหนดรับ (ว/ด/ป):</strong> <span class="font-bold text-blue-600">{{ is_numeric($piRow[6]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[6])->format('d-m-Y') : $piRow[6] }}</span></div>
                <div><strong>วันรับ (ว/ด/ป):</strong> <span class="font-bold text-blue-600">{{ is_numeric($piRow[7]) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($piRow[7])->format('d-m-Y') : $piRow[7] }}</span></div>
                <div><strong>รหัส PO:</strong> <span class="font-bold text-blue-600">{{ $piRow[8] ?? '-' }}</span></div>
                <div><strong>ค่าส่ง:</strong> <span class="font-bold text-blue-600">{{ $piRow[13] ?? '-' }}</span></div>
                <div><strong>ค่าประกัน:</strong> <span class="font-bold text-blue-600">{{ $piRow[14] ?? '-' }}</span></div>
                <div><strong>เงินฝาก:</strong> <span class="font-bold text-blue-600">{{ $piRow[15] ?? '-' }}</span></div>
                <div><strong>จำนวนเงินทั้งหมด:</strong> <span class="font-bold text-blue-600">{{ number_format($totalAmount, 2) }} USD</span></div>
                <div class="md:col-span-2"><strong>รายละเอียด PI:</strong> <span class="font-bold text-blue-600">{{ $piRow[12] ?? '-' }}</span></div>
            </div>
        </div>
        @php
            // All data rows (skip the header)
            $productRows = array_slice($rows, 1);

            // Count rows that have a product code in column Q (index 16)
            $productCount = 0;
            foreach ($productRows as $r) {
                if (isset($r[16]) && trim((string)$r[16]) !== '') {
                    $productCount++;
                }
            }
        @endphp
        {{-- ✅ Product Table Section --}}
        <div class="space-y-2 border border-blue-300 rounded-md p-4 bg-blue-50">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-blue-700 flex items-center gap-2">
                    📦 รายการสินค้า
                    <span id="product-count" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $productCount }} รายการ
                    </span>
                </h2>

                {{-- Right-aligned search (ProductNumber) --}}
                <div class="relative w-full max-w-xs">
                    <input
                        id="product-search"
                        type="text"
                        placeholder="ค้นหา ProductNumber…"
                        class="w-full border border-blue-300 bg-white rounded-md pl-3 pr-9 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500">🔎</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="max-h-[240px] overflow-y-auto border border-gray-300 rounded-md">
                    <table id="preview-products" class="w-full border-collapse text-sm bg-white">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border px-4 py-2 text-left">รหัสสินค้า</th>
                                <th class="border px-4 py-2 text-left">รายละเอียดสินค้า</th>
                                <th class="border px-4 py-2 text-left">รหัสสินค้าลูกค้า</th>
                                <th class="border px-4 py-2 text-left">น้ำหนัก</th>
                                <th class="border px-4 py-2 text-left">จำนวน</th>
                                <th class="border px-4 py-2 text-left">หน่วยต่อราคา</th>
                                <th class="border px-4 py-2 text-left">จำนวนเงิน</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            @foreach(array_slice($rows, 1) as $row)
                                @php $pn = trim((string)($row[16] ?? '')); @endphp
                                <tr class="hover:bg-gray-50" data-productnumber="{{ $pn }}">
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
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('product-search');
    const table = document.getElementById('preview-products');
    const rows  = table?.querySelectorAll('tbody tr') || [];
    const countBadge = document.getElementById('product-count');

    function applyFilter() {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach(tr => {
        const pn = (tr.dataset.productnumber || '').toLowerCase();
        const show = pn.includes(q);
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
        });

        if (countBadge) {
        countBadge.textContent = `${visible} รายการ`;
        }
    }

    input?.addEventListener('input', applyFilter);
    });
    </script>

</x-app-layout>
