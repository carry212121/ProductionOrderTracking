<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('dashboard.index') }}" class="hover:underline text-blue-600">สรุปรายการ Proforma Invoice</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าของ {{ $sourceName }}</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการสินค้าของ {{ $sourceName }}
        </h2>
        <div class="flex items-center gap-2">
            <x-filter toggleId="filterToggleBtn" panelId="filterProductPanel">
                <button class="filter-option w-full text-left hover:bg-gray-200 px-2 py-1" data-filter="all">📦 แสดงทั้งหมด</button>
                <button class="filter-option w-full text-left hover:bg-yellow-100 px-2 py-1" data-filter="yellow">🟡 ช้า 1–7 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-100 px-2 py-1" data-filter="red">🔴 ช้า 8–14 วัน</button>
                <button class="filter-option w-full text-left hover:bg-red-400 px-2 py-1" data-filter="darkred">🟥 ช้าเกิน 15 วัน</button>
            </x-filter>
            <x-search-bar id="product-search" placeholder="ค้นหารหัสสินค้า..." class="w-64" />
        </div>
    </div>
    @if($products->isNotEmpty())
        <div class="py-6 px-6">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Left Side: Summary + Pie Chart -->
                <div class="w-full lg:w-1/3 flex flex-col gap-6">
                    <!-- Summary Block -->
                    <div class="bg-white shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold">ภาพรวมสินค้า</h3>
                            <select id="monthFilter" class="border rounded px-3 py-1 text-sm">
                                <option value="all">ทุกเดือน</option>
                                @foreach ($availableMonths as $month)
                                    <option value="{{ $month }}">{{ \Carbon\Carbon::parse($month . '-01')->locale('th')->isoFormat('MMMM YYYY') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-100 rounded p-4 text-center">
                                <p class="text-sm text-gray-500">สินค้าทั้งหมด</p>
                                <p id="totalCount" class="text-2xl font-bold">{{ $products->count() }}</p>
                            </div>
                            <div class="bg-green-100 rounded p-4 text-center">
                                <p class="text-sm text-gray-500">ตรงเวลา</p>
                                <p id="onTimeCount" class="text-2xl font-bold text-green-700">{{ $onTime }}</p>
                            </div>
                            <div class="bg-red-100 rounded p-4 text-center">
                                <p class="text-sm text-gray-500">เลท</p>
                                <p id="lateCount" class="text-2xl font-bold text-red-700">{{ $lateYellow + $lateRed + $lateDarkRed }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pie Chart -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">สัดส่วนสินค้าตรงเวลา vs เลท</h3>
                        <canvas id="latenessPieChart" width="400" height="300"></canvas>
                    </div>
                </div>

                <!-- Right: Product List -->
                <div class="bg-white shadow rounded-lg p-6 w-full lg:w-2/3">
                    <h3 class="text-lg font-semibold mb-6">
                        {{ $groupBy === 'production' ? 'สินค้าโดยผู้รับผิดชอบ Production'  : 'สินค้าในโรงงาน' }}
                    </h3>
                    <div id="no-result-message" class="text-center text-gray-500 mt-6 hidden">
                        ไม่พบรหัสสินค้าที่ค้นหา
                    </div>
                    <div id="no-results" class="text-gray-500 text-center my-4 hidden">
                        ไม่พบรายการสินค้าที่ตรงกับตัวกรอง
                    </div>
                    <div class="max-h-[600px] overflow-y-auto pr-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach ($products as $product)
                                @php
                                    $today = Carbon\Carbon::today();
                                    $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
                                    $jobControls = $product->jobControls->keyBy('Process');
                                    $latestProcess = null;
                                    foreach ($processOrder as $process) {
                                        if (!empty($jobControls[$process]?->AssignDate)) {
                                            $latestProcess = $process;
                                        }
                                    }

                                    $daysLate = 0;
                                    $bgClass = 'bg-gray-50';

                                    if ($latestProcess && isset($jobControls[$latestProcess])) {
                                        $job = $jobControls[$latestProcess];
                                        $scheduleDate = $job->ScheduleDate ? Carbon\Carbon::parse($job->ScheduleDate) : null;
                                        $receiveDate = $job->ReceiveDate;

                                        if ($scheduleDate && $scheduleDate->lt($today) && !$receiveDate) {
                                            $daysLate = $scheduleDate->diffInDays($today);

                                            if ($daysLate >= 15) {
                                                $bgClass = 'bg-red-400';
                                            } elseif ($daysLate >= 8) {
                                                $bgClass = 'bg-red-200';
                                            } elseif ($daysLate >= 1) {
                                                $bgClass = 'bg-yellow-100';
                                            }
                                        }
                                    }
                                @endphp

                                <div class="border rounded-lg shadow p-4 {{ $bgClass }} hover:shadow-lg transition product-card "
                                    data-product-number="{{ $product->ProductNumber }}"
                                    data-month="{{ optional($product->proformaInvoice?->created_at)->format('Y-m') }}"
                                    data-pi-number="{{ $product->proformaInvoice?->PInumber ?? '-' }}"
                                        data-status="{{ 
                                        $bgClass === 'bg-red-400' ? 'darkred' : 
                                        ($bgClass === 'bg-red-200' ? 'red' : 
                                        ($bgClass === 'bg-yellow-100' ? 'yellow' : 'ontime')) 
                                    }}">
                                    <p class="text-sm text-gray-700 font-semibold">PI Number:</p>
                                    <p class="text-sm text-gray-800 mb-1">
                                        {{ $product->proformaInvoice?->PInumber ?? '-' }}
                                    </p>

                                    <p class="text-sm text-gray-700 font-semibold">Product Number:</p>
                                    <p class="text-sm text-gray-800 mb-1">
                                        {{ $product->ProductNumber }}
                                    </p>

                                    <p class="text-sm text-gray-700 font-semibold">
                                        {{ $groupBy === 'production' ? 'Production ID:' : 'Factory Number:' }}
                                    </p>
                                    <p class="text-sm text-gray-800">
                                        @if($groupBy === 'production')
                                            {{ $product->proformaInvoice?->user->productionID ?? '-' }}
                                        @else
                                            {{
                                                optional(
                                                    $product->jobControls->firstWhere('factory_id', request()->id)
                                                )?->factory?->FactoryNumber ?? '-'
                                            }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="py-6 px-6">
            <div class="bg-white shadow rounded-lg p-6">
                <p class="text-gray-400">ไม่มีสินค้า</p>
            </div>
        </div>
    @endif
    <script>
        const ctx = document.getElementById('latenessPieChart').getContext('2d');
        const latenessChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['ตรงเวลา', 'เลท 1-7 วัน', 'เลท 8-14 วัน', 'เลทเกิน 15 วัน'],
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

        document.addEventListener('DOMContentLoaded', () => {
            let selectedMonth = 'all';
            let selectedStatus = 'all';

            const updateView = () => {
                const cards = document.querySelectorAll('.product-card');
                let onTime = 0, yellow = 0, red = 0, darkred = 0, total = 0, visibleCount = 0;

                cards.forEach(card => {
                    const month = card.dataset.month;
                    const status = card.dataset.status;

                    const matchMonth = selectedMonth === 'all' || month === selectedMonth;
                    const matchStatus = selectedStatus === 'all' || status === selectedStatus;

                    if (matchMonth && matchStatus) {
                        card.classList.remove('hidden');
                        visibleCount++;
                        total++;
                        if (status === 'ontime') onTime++;
                        else if (status === 'yellow') yellow++;
                        else if (status === 'red') red++;
                        else if (status === 'darkred') darkred++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                document.getElementById('totalCount').innerText = total;
                document.getElementById('onTimeCount').innerText = onTime;
                document.getElementById('lateCount').innerText = yellow + red + darkred;

                latenessChart.data.datasets[0].data = [onTime, yellow, red, darkred];
                latenessChart.update();

                const noResult = document.getElementById('no-results');
                noResult.classList.toggle('hidden', visibleCount !== 0);
            };

            // Handle month filter
            document.getElementById('monthFilter').addEventListener('change', (e) => {
                selectedMonth = e.target.value;
                updateView();
            });

            // Handle lateness filter
            document.querySelectorAll('.filter-option').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedStatus = btn.dataset.filter;
                    updateView();
                });
            });

            // Toggle filter panel
            const toggleBtn = document.getElementById('filterToggleBtn');
            const panel = document.getElementById('filterProductPanel');
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                panel.classList.toggle('opacity-0');
                panel.classList.toggle('scale-95');
                panel.classList.toggle('pointer-events-none');
            });

            document.addEventListener('click', () => {
                panel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            });
        });
    </script>

</x-app-layout>
