<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            สรุปรายการ Proforma invoice
        </h2>
    </x-slot>

    <div class="py-6 px-6">
        <div class="flex flex-col md:flex-row gap-6">

            <!-- Block #1 -->
            <div class="bg-white shadow-xl sm:rounded-lg p-6 w-full md:w-1/2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">ภาพรวม Proforma invoice</h3>
                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('dashboard') }}" id="monthFilterForm" class="flex items-center space-x-2">
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
                    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center space-x-2">
                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                        <label for="groupBy" class="text-sm text-gray-700">แสดงตาม:</label>
                        <select name="groupBy" id="groupBy" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <option value="factory" {{ $groupBy === 'factory' ? 'selected' : '' }}>โรงงาน</option>
                            <option value="production" {{ $groupBy === 'production' ? 'selected' : '' }}>Production</option>
                        </select>
                    </form>
                </div>
                <ul class="list-disc pl-6 text-sm text-gray-800">
                    @forelse ($grouped as $name)
                        <li>{{ $name }}</li>
                    @empty
                        <li class="text-gray-400">ไม่มีข้อมูล</li>
                    @endforelse
                </ul>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('piStatusPieChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['PI ตรงเวลา', 'PI ที่เลท'],
                datasets: [{
                    label: 'จำนวน',
                    data: [{{ $onTime }}, {{ $late }}],
                    backgroundColor: ['rgba(34, 197, 94, 0.7)', 'rgba(239, 68, 68, 0.7)'],
                    borderColor: ['rgba(34, 197, 94, 1)', 'rgba(239, 68, 68, 1)'],
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
                        backgroundColor: 'rgba(34, 197, 94, 0.7)', // green
                    },
                    {
                        label: 'เลท',
                        data: {!! json_encode($barChartLate) !!},
                        backgroundColor: 'rgba(239, 68, 68, 0.7)', // red
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
