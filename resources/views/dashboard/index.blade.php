<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <span class="text-gray-800 font-medium">สรุปรายการ Proforma Invoice</span>
        </nav>
    </x-slot>

    <div class="py-6 px-6">
        <div class="flex flex-col md:flex-row gap-6">

            <!-- Block #1 -->
            <div class="bg-white shadow-xl sm:rounded-lg p-6 w-full md:w-1/2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">ภาพรวม Proforma invoice</h3>
                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('dashboard.index') }}" id="monthFilterForm" class="flex items-center space-x-2">
                        <label for="month" class="font-medium text-gray-700 text-sm">เลือกเดือน:</label>
                        <select name="month" id="month" onchange="document.getElementById('monthFilterForm').submit()" class="border border-gray-300 rounded px-3 py-1 text-sm">
                            <option value="">ทุกเดือน</option>
                            @for ($m = 1; $m <= 12; $m++)
                                @php
                                    $monthStr = now()->year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                @endphp
                                <option value="{{ $monthStr }}" {{ $selectedMonth === $monthStr ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($monthStr . '-01')->locale('th')->isoFormat('MMMM YYYY') }}
                                </option>
                            @endfor
                        </select>
                    </form>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-100 rounded p-4 text-center">
                        <p class="text-sm text-gray-500">PI ทั้งหมด</p>
                        <p class="text-2xl font-bold">{{ $total }}</p>
                    </div>
                    <div class="bg-green-100 rounded p-4 text-center">
                        <p class="text-sm text-gray-500">PI ที่ตรงเวลา</p>
                        <p class="text-2xl font-bold text-green-700">{{ $onTime }}</p>
                    </div>
                    <div class="bg-red-100 rounded p-4 text-center">
                        <p class="text-sm text-gray-500">PI ที่เลท</p>
                        <p class="text-2xl font-bold text-red-700">{{ $late }}</p>
                    </div>
                </div>
            </div>

            <!-- Block #3 -->
            <div class="bg-white shadow-xl sm:rounded-lg p-6 w-full md:w-1/2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">
                        รายการ Proforma invoice ตาม{{ $groupBy === 'production' ? 'ผู้รับผิดชอบ Production' : 'โรงงาน' }}
                    </h3>
                    <form method="GET" action="{{ route('dashboard.index') }}" class="flex items-center space-x-2">
                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                        <label for="groupBy" class="text-sm text-gray-700">แสดงตาม:</label>
                        <select name="groupBy" id="groupBy" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <option value="factory" {{ $groupBy === 'factory' ? 'selected' : '' }}>โรงงาน</option>
                            <option value="production" {{ $groupBy === 'production' ? 'selected' : '' }}>Production</option>
                        </select>
                    </form>
                </div>
                <div class="overflow-y-auto max-h-36 pr-2">
                    <ul class="list-disc pl-6 text-sm text-gray-800 space-y-1">
                        @forelse ($grouped as $id => $name)
                            <li>
                                <a href="{{ route('dashboard.detail', ['id' => $id, 'groupBy' => $groupBy]) }}" class="text-blue-600 hover:underline">
                                    {{ $name }}
                                </a>
                            </li>
                        @empty
                            <li class="text-gray-400">ไม่มีข้อมูล</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Block #2: Pie Chart -->
            <div class="bg-white shadow-xl sm:rounded-lg p-6 w-full md:w-1/2 mt-6">
                <h3 class="text-lg font-semibold mb-4">สัดส่วน PI ตรงเวลา vs เลท</h3>
                <canvas id="piStatusPieChart" width="400" height="300"></canvas>
            </div>
            <div class="bg-white shadow-xl sm:rounded-lg p-6 w-full md:w-1/2 mt-6">
                <h3 class="text-lg font-semibold mb-4">
                    กราฟเปรียบเทียบ PI ตรงเวลา/เลท ตาม{{ $groupBy === 'production' ? 'Production' : 'โรงงาน' }}
                </h3>
                <canvas id="barChartPiStatus" width="400" height="300"></canvas>
                @if ($totalPages > 1)
                    <div class="mt-4 flex justify-center gap-2">
                        @for ($i = 1; $i <= $totalPages; $i++)
                            <form method="GET" action="{{ route('dashboard.index') }}">
                                <input type="hidden" name="month" value="{{ $selectedMonth }}">
                                <input type="hidden" name="groupBy" value="{{ $groupBy }}">
                                <input type="hidden" name="barPage" value="{{ $i }}">
                                <button type="submit" class="px-3 py-1 border rounded {{ $i == $page ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $i }}
                                </button>
                            </form>
                        @endfor
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('piStatusPieChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['PI ตรงเวลา', 'เลท 1-7 วัน', 'เลท 8-14 วัน', 'เลทเกิน 15 วัน'],
                datasets: [{
                    label: 'จำนวน',
                    data: [{{ $onTime }}, {{ $lateYellow }}, {{ $lateRed }}, {{ $lateDarkRed }}],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.7)',   // Green
                        'rgba(253, 224, 71, 0.9)',  // Yellow
                        'rgba(239, 68, 68, 0.9)',   // Red
                        'rgba(127, 29, 29, 0.9)'    // Dark Red
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(253, 224, 71, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(127, 29, 29, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        const barCtx = document.getElementById('barChartPiStatus').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($barChartLabels) !!},
                datasets: [
                    {
                        label: 'ตรงเวลา',
                        data: {!! json_encode($barChartOnTime) !!},
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    },
                    {
                        label: 'เลท 1-7 วัน',
                        data: {!! json_encode($barChartLateYellow) !!},
                        backgroundColor: 'rgba(253, 224, 71, 0.9)',
                    },
                    {
                        label: 'เลท 8-14 วัน',
                        data: {!! json_encode($barChartLateRed) !!},
                        backgroundColor: 'rgba(239, 68, 68, 0.9)',
                    },
                    {
                        label: 'เลทเกิน 15 วัน',
                        data: {!! json_encode($barChartLateDarkRed) !!},
                        backgroundColor: 'rgba(127, 29, 29, 0.9)',
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 90,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'จำนวน PI'
                        }
                    }
                }
            }
        });
    </script>
</x-app-layout>
