<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <a href="{{ route('dashboard.index') }}" class="hover:underline text-blue-600">สรุปรายการ สินค้า</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">รายการสินค้าตามกระบวนการ</span>
        </nav>
    </x-slot>
    <!-- Product Detail Modal -->
    <div id="product-detail-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-xl p-6 relative overflow-y-auto max-h-[90vh]">
            <button id="close-modal" class="absolute top-2 right-2 text-gray-500 hover:text-black text-2xl">&times;</button>
            <!-- Modal Title -->
            <div class="mb-4 text-center">
                <h2 class="text-lg font-semibold">
                    <span id="modal-pi-number" class="text-blue-600"></span> - 
                    <span id="modal-product-number" class="text-gray-800"></span>
                </h2>
            </div>

            <!-- Modal Content Split Left & Right -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm text-gray-800">
                <!-- Left Side -->
                <div class="space-y-2">
                    <strong>รายละเอียด:</strong>
                    <div class="h-48 overflow-auto border rounded p-2">
                        <p id="modal-description" class="whitespace-pre-line"></p>
                    </div>
                    <div><strong>รหัสบิล:</strong> <span id="modal-billnumber"></span></div>
                    <div><strong>จำนวน:</strong> <span id="modal-qty"></span></div>
                    <div><strong>น้ำหนัก:</strong> <span id="modal-weight"></span></div>
                    <div><strong>จำนวนสั่ง:</strong> <span id="modal-qtyorder"></span></div>
                    <div><strong>วันกำหนดรับ:</strong> <span id="modal-scheduledate"></span></div>
                    <div><strong>น้ำหนักทั้งหมดก่อน:</strong> <span id="modal-beforeweight"></span></div>
                </div>

                <!-- Right Side -->
                <div class="space-y-2">
                    <div>
                        <strong>รูปภาพสินค้า:</strong><br>
                        <img id="modal-image" src="" alt="Product Image" class="mt-1 w-full max-h-48 object-contain border rounded">
                    </div>
                    <div><strong>โรงงาน:</strong> <span id="modal-factory"></span></div>
                    <div><strong>กระบวนการ:</strong> <span id="modal-process"></span></div>
                    <div><strong>จำนวนรับ:</strong> <span id="modal-qtyreceive"></span></div>
                    <div><strong>วันรับ:</strong> <span id="modal-receivedate"></span></div>
                    <div><strong>น้ำหนักทั้งหมดหลัง:</strong> <span id="modal-afterweight"></span></div>
                </div>
            </div>

        </div>
    </div>


    <div class="flex justify-between items-center px-6 mt-4 gap-4 flex-wrap">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
            รายการสินค้า ตามกระบวนการ
        </h2>
        <div class="flex items-center gap-2">
            <x-search-bar id="product-process-search" placeholder="ค้นหารหัสสินค้า/PI/..." class="w-64" />
        </div>
    </div>
    <div class="py-6 px-6">
        <div class="bg-white shadow sm:rounded-lg p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                @foreach ($groupedProducts as $stage => $products)
                    <div>
                        <h4 class="text-lg font-semibold mb-2">{{ $stage }}</h4>
                        <div class="stage-wrapper h-[600px] overflow-y-auto border p-2 rounded bg-gray-50">
                            @forelse ($products as $product)
                                <div class="product-card border rounded p-2 mb-2 {{ $product->bgClass }}"
                                    data-product-number="{{ $product->ProductNumber }}"
                                    data-description="{{ $product->Description }}"
                                    data-pi-number="{{ $product->proformaInvoice?->PInumber }}"
                                    data-billnumber="{{ $product->jobControls->last()?->Billnumber }}"
                                    data-qty="{{ $product->Quantity }}"
                                    data-weight="{{ $product->Weight }}"
                                    data-factory="{{ $product->jobControls->last()?->factory?->FactoryName }}"
                                    data-process="{{ $product->jobControls->last()?->Process }}"
                                    data-qtyorder="{{ $product->jobControls->last()?->QtyOrder }}"
                                    data-qtyreceive="{{ $product->jobControls->last()?->QtyReceive }}"
                                    data-scheduledate="{{ $product->jobControls->last()?->ScheduleDate }}"
                                    data-receivedate="{{ $product->jobControls->last()?->ReceiveDate }}"
                                    data-beforeweight="{{ $product->jobControls->last()?->TotalWeightBefore }}"
                                    data-afterweight="{{ $product->jobControls->last()?->TotalWeightAfter }}"
                                    data-image="{{ asset('storage/' . $product->Image) }}"
                                >
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ $product->ProductNumber }}
                                    </p>
                                    <p class="text-xs text-gray-600">
                                        PI: {{ $product->proformaInvoice?->PInumber ?? '-' }}
                                    </p>
                                    <p class="text-xs text-gray-600">
                                        Production ID: {{ $product->proformaInvoice?->user?->productionID ?? '-' }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-gray-400 text-sm">-</p>
                            @endforelse
                            <p class="no-result-message text-red-500 text-sm hidden">ไม่พบสินค้าในกระบวนการนี้</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchInput = document.getElementById('product-process-search');

        searchInput.addEventListener('input', function () {
            const searchTerm = searchInput.value.toLowerCase();

            document.querySelectorAll('.stage-wrapper').forEach(stage => {
                let visibleCount = 0;

                stage.querySelectorAll('.product-card').forEach(card => {
                    const productNumber = (card.getAttribute('data-product-number') || '').toLowerCase();
                    const piNumber = (card.getAttribute('data-pi-number') || '').toLowerCase();
                    const productionId = (card.getAttribute('data-production-id') || '').toLowerCase();

                    const match = productNumber.includes(searchTerm)
                        || piNumber.includes(searchTerm)
                        || productionId.includes(searchTerm);

                    card.style.display = match ? 'block' : 'none';
                    if (match) visibleCount++;
                });

                const message = stage.querySelector('.no-result-message');
                if (message) {
                    message.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            });
        });

        // Show modal on click
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function () {
                const get = (attr) => card.getAttribute(attr) || '-';
                
                document.getElementById('modal-product-number').textContent = get('data-product-number');
                document.getElementById('modal-description').textContent = get('data-description');
                document.getElementById('modal-pi-number').textContent = get('data-pi-number');
                document.getElementById('modal-billnumber').textContent = get('data-billnumber');
                document.getElementById('modal-qty').textContent = get('data-qty');
                document.getElementById('modal-weight').textContent = get('data-weight');
                document.getElementById('modal-factory').textContent = get('data-factory');
                document.getElementById('modal-process').textContent = get('data-process');
                document.getElementById('modal-qtyorder').textContent = get('data-qtyorder');
                document.getElementById('modal-qtyreceive').textContent = get('data-qtyreceive');
                document.getElementById('modal-scheduledate').textContent = get('data-scheduledate');
                document.getElementById('modal-receivedate').textContent = get('data-receivedate');
                document.getElementById('modal-beforeweight').textContent = get('data-beforeweight');
                document.getElementById('modal-afterweight').textContent = get('data-afterweight');

                const image = card.getAttribute('data-image');
                const imageElement = document.getElementById('modal-image');
                imageElement.src = image || '{{ asset("images/default-product.jpg") }}';
                imageElement.onerror = function () {
                    this.onerror = null;
                    this.src = '{{ asset("images/default-product.jpg") }}';
                };


                document.getElementById('product-detail-modal').classList.remove('hidden');
            });
        });

        // Close modal
        document.getElementById('close-modal').addEventListener('click', function () {
            document.getElementById('product-detail-modal').classList.add('hidden');
        });

        // Close on background click
        document.getElementById('product-detail-modal').addEventListener('click', function (e) {
            if (e.target.id === 'product-detail-modal') {
                document.getElementById('product-detail-modal').classList.add('hidden');
            }
        });
    });
    </script>

</x-app-layout>

