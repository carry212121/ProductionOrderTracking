<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('dashboard.index') }}" class="hover:underline text-blue-600">สรุปรายการ {{ request('label', 'Proforma invoice') }}</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าของ {{ $sourceName }}</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Product Detail Modal -->
    <div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl p-6 relative">
            <!-- Close Button -->
            <button onclick="closeProductModal()" class="absolute top-2 right-3 text-gray-600 hover:text-red-600 text-xl">&times;</button>
            <h3 class="text-lg font-bold mb-4 text-center">รายละเอียดสินค้า</h3>

            <!-- Flex layout: Left details, Right image -->
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Left Side: Details -->
                <div class="space-y-2 text-sm flex-1">
                    <p><strong>รหัส PI:</strong> <span id="modalPInumber"></span></p>
                    <p><strong>รหัสสินค้า:</strong> <span id="modalProductNumber"></span></p>
                    <p><strong>รายละเอียดสินค้า:</strong> <span id="modalDescription"></span></p>
                    <p><strong>รหัสสินค้าลูกค้า:</strong> <span id="modalProductCustomerNumber"></span></p>
                    <p><strong>น้ำหนัก:</strong> <span id="modalWeight"></span></p>
                    <p><strong>จำนวน:</strong> <span id="modalQuantity"></span></p>
                    <p><strong>หน่วยต่อราคา:</strong> <span id="modalUnitPrice"></span></p>
                </div>

                <!-- Right Side: Image -->
                <div class="flex-1 flex justify-center items-center">
                    <img id="modalImage" src="" alt="Product Image"
                        class="w-full max-w-xs max-h-72 object-contain border rounded shadow"
                        onerror="this.src='/images/default-product.jpg';">
                </div>
            </div>
        </div>
    </div>


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
            <x-search-bar id="product-search" placeholder="ค้นหารหัสสินค้า/PI..." class="w-64" />
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
                            <div class="bg-red-100 rounded p-4 text-center">
                                <p class="text-sm text-gray-500">เลท</p>
                                <p id="lateCount" class="text-2xl font-bold text-red-700">{{ $lateYellow + $lateRed + $lateDarkRed }}</p>
                            </div>
                            <div class="bg-green-100 rounded p-4 text-center">
                                <p class="text-sm text-gray-500">ตรงเวลา</p>
                                <p id="onTimeCount" class="text-2xl font-bold text-green-700">{{ $onTime }}</p>
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
                                    $bgClass = match($product->late_status) {
                                        'darkred' => 'bg-red-400',
                                        'red' => 'bg-red-200',
                                        'yellow' => 'bg-yellow-100',
                                        default => 'bg-white',
                                    };
                                @endphp

                                <div onclick="showProductModal(this)" class="border rounded-lg shadow p-4 {{ $bgClass }} hover:shadow-lg transition product-card "
                                    data-product-number="{{ $product->ProductNumber }}"
                                    data-month="{{ optional($product->proformaInvoice?->created_at)->format('Y-m') }}"
                                    data-pi-number="{{ $product->proformaInvoice?->PInumber ?? '-' }}"
                                    data-status="{{ 
                                        $bgClass === 'bg-red-400' ? 'darkred' : 
                                        ($bgClass === 'bg-red-200' ? 'red' : 
                                        ($bgClass === 'bg-yellow-100' ? 'yellow' : 'ontime')) 
                                    }}"
                                    data-description="{{ $product->Description }}"
                                    data-product-customer-number="{{ $product->ProductCustomerNumber }}"
                                    data-weight="{{ $product->Weight }}"
                                    data-quantity="{{ $product->Quantity }}"
                                    data-unit-price="{{ $product->UnitPrice }}"
                                    data-image="{{ $product->Image }}">
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
            let searchTerm = '';
            const searchInput = document.getElementById('product-search');
            const cards = document.querySelectorAll('.product-card');
            const updateView = () => {
                const cards = document.querySelectorAll('.product-card');
                let onTime = 0, yellow = 0, red = 0, darkred = 0, total = 0, visibleCount = 0;
                // console.log('🔍 Filter values →', {
                //     selectedMonth,
                //     selectedStatus,
                //     searchTerm
                // });
                cards.forEach(card => {
                    const month = card.dataset.month;
                    const status = card.dataset.status;
                    const productNumber = card.dataset.productNumber.toLowerCase();
                    const piNumber = card.dataset.piNumber.toLowerCase();
                    
                    const matchMonth = selectedMonth === 'all' || month === selectedMonth;
                    const matchStatus = selectedStatus === 'all' || status === selectedStatus;
                    const search = searchTerm.toLowerCase();
                    const matchSearch = !search || productNumber.includes(search) || piNumber.includes(search);

                    // console.log(`📦 Checking card → PI: ${piNumber}, Product: ${productNumber}`);
                    // console.log(`✅ Match → month: ${matchMonth}, status: ${matchStatus}, search: ${matchSearch}`);
                    if (matchMonth && matchStatus && matchSearch) {
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
                // console.log(`👀 Visible cards: ${visibleCount}`);
                document.getElementById('totalCount').innerText = total;
                document.getElementById('onTimeCount').innerText = onTime;
                document.getElementById('lateCount').innerText = yellow + red + darkred;

                latenessChart.data.datasets[0].data = [onTime, yellow, red, darkred];
                latenessChart.update();

                const noResultFilter = document.getElementById('no-results');
                const noResultSearch = document.getElementById('no-result-message');

                const hasSearch = searchTerm.trim().length > 0;
                const hasFilter = selectedMonth !== 'all' || selectedStatus !== 'all';

                // Only show search-related message when there's a search term and no results
                noResultSearch.classList.toggle('hidden', !(hasSearch && visibleCount === 0));

                // Only show filter-related message when there's a filter but no search term
                noResultFilter.classList.toggle('hidden', !(hasFilter && !hasSearch && visibleCount === 0));
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

            searchInput.addEventListener('input', (e) => {
                searchTerm = e.target.value.trim();
                updateView();
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
        function showProductModal(card) {
            document.getElementById('modalPInumber').innerText = card.dataset.piNumber || '-';
            document.getElementById('modalProductNumber').innerText = card.dataset.productNumber || '-';
            document.getElementById('modalDescription').innerText = card.dataset.description || '-';
            document.getElementById('modalProductCustomerNumber').innerText = card.dataset.productCustomerNumber || '-';
            document.getElementById('modalWeight').innerText = card.dataset.weight || '-';
            document.getElementById('modalQuantity').innerText = card.dataset.quantity || '-';
            document.getElementById('modalUnitPrice').innerText = card.dataset.unitPrice || '-';
            
            const modalImage = document.getElementById('modalImage');
            const imgSrc = card.dataset.image || '';
            modalImage.src = imgSrc ? `/storage/app/public/product_images/${imgSrc}` : '/images/default-product.jpg';
            modalImage.onerror = () => {
                modalImage.src = '/images/default-product.jpg';
            };


            document.getElementById('productModal').classList.remove('hidden');
            document.getElementById('productModal').classList.add('flex');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            document.getElementById('productModal').classList.remove('flex');
        }
        document.addEventListener('DOMContentLoaded', () => {

            // Modal close on outside click
            const modal = document.getElementById('productModal');
            const modalContent = modal.querySelector('.bg-white');

            modal.addEventListener('click', function (e) {
                // If the clicked target is NOT inside the modal content, close it
                if (!modalContent.contains(e.target)) {
                    closeProductModal();
                }
            });
        });
    </script>

</x-app-layout>
