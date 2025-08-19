{{-- resources/views/users/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-600 flex items-center space-x-2">
            <span class="text-gray-800 font-medium">รายการ บัญชีผู้ใช้</span>
        </nav>
    </x-slot>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @if(session('changes'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                let data = @json(session('changes'));
                let html = '';
                for (const [field, diff] of Object.entries(data.changes)) {
                    html += `<div class="text-left mb-1"><b>${field}</b>: ${diff.old ?? '—'} → <span class="text-green-600">${diff.new ?? '—'}</span></div>`;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'อัปเดตสำเร็จ',
                    html: `
                        <p class="mb-3"><b>${data.name}</b> ถูกแก้ไขแล้ว</p>
                        <div class="space-y-2">
                            ${html}
                        </div>
                    `,
                    confirmButtonText: 'ตกลง',
                });
            });
        </script>
    @endif
    @if(session('role_error'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่สามารถบันทึกได้',
                    text: @json(session('role_error')),
                    confirmButtonText: 'ตกลง',
                });
            });
        </script>
    @endif
    @if(session('created'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const u = @json(session('created'));
                Swal.fire({
                    icon: 'success',
                    title: 'สร้างผู้ใช้สำเร็จ',
                    html: `
                        <div class="text-left space-y-1">
                            <div><b>ชื่อ:</b> ${u.name}</div>
                            <div><b>ชื่อผู้ใช้:</b> ${u.username}</div>
                            <div><b>บทบาท:</b> ${u.role}</div>
                        </div>
                    `,
                    confirmButtonText: 'ตกลง'
                });
            });
        </script>
    @endif

    <div class="px-6 py-6" x-data="{ openCreateUser: {{ session()->has('show_create_modal') ? 'true' : 'false' }} }">
        <div class="mb-4 text-sm text-gray-700 flex flex-wrap items-center gap-4">
            {{-- Total --}}
            <div>
                ทั้งหมด 
                <span class="font-semibold text-gray-900">{{ $users->count() }}</span> คน
                @if(!empty($q))
                    <span class="ml-2 text-gray-500">
                        (ผลลัพธ์สำหรับ: <span class="font-semibold">{{ $q }}</span>)
                    </span>
                @endif
            </div>

            {{-- Count per role --}}
            @php
                $roleCounts = $users->groupBy('role')->map->count();
            @endphp

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-md bg-purple-100 text-purple-800 text-xs font-medium">
                    Head: <span class="ml-1 font-semibold">{{ $roleCounts['Head'] ?? 0 }}</span>
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-md bg-blue-100 text-blue-800 text-xs font-medium">
                    Admin: <span class="ml-1 font-semibold">{{ $roleCounts['Admin'] ?? 0 }}</span>
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-md bg-amber-100 text-amber-800 text-xs font-medium">
                    Production: <span class="ml-1 font-semibold">{{ $roleCounts['Production'] ?? 0 }}</span>
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-md bg-green-100 text-green-800 text-xs font-medium">
                    Sales: <span class="ml-1 font-semibold">{{ $roleCounts['Sales'] ?? 0 }}</span>
                </span>
            </div>
            <button @click="openCreateUser = true"
                class="ml-auto inline-flex items-center gap-2 px-4 py-2 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">
                + สร้างผู้ใช้
            </button>
        </div>

        <!-- Modal overlay -->
        <div x-show="openCreateUser" x-cloak class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50"
            x-transition>
            <div x-cloak class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
                <h2 class="text-lg font-semibold mb-4">สร้างผู้ใช้ใหม่</h2>

                <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                    <label class="block text-sm font-medium">ชื่อ *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    </div>

                    <div>
                    <label class="block text-sm font-medium">ชื่อผู้ใช้ *</label>
                    <input type="text" name="username" value="{{ old('username') }}" required
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
                    @error('username')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    </div>

                    <div>
                    <label class="block text-sm font-medium">รหัสผ่าน *</label>
                    <input type="password" name="password" required
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
                    @error('password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    </div>

                    <div>
                    <label class="block text-sm font-medium">บทบาท *</label>
                    <select name="role" required
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        @foreach (['Admin','Production','Sales','Head'] as $r)
                        <option value="{{ $r }}" @selected(old('role')===$r)>{{ $r }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Production ID</label>
                        <input type="text" name="productionID" value="{{ old('productionID') }}"
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
                        @error('productionID')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Sales ID</label>
                        <input type="text" name="salesID" value="{{ old('salesID') }}"
                            class="mt-1 block w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
                        @error('salesID')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                    <button type="button" @click="openCreateUser = false"
                            class="px-4 py-2 border rounded-md text-sm">ยกเลิก</button>
                    <button type="submit"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm hover:bg-emerald-700">บันทึก</button>
                    </div>
                </form>

                <!-- Close button (X) -->
                <button @click="openCreateUser = false"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">&times;</button>
            </div>
        </div>



        {{-- Scrollable Table Block --}}
        <div class="bg-white shadow rounded-lg border w-full h-[500px] flex flex-col">
            <div class="overflow-y-auto flex-1">
                <table class="w-full table-fixed divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลำดับ</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อผู้ใช้</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Production ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales ID</th>
                            <th class="w-52 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">บทบาท</th>
                            <th class="w-28 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($users as $i => $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-700 align-top">{{ $i + 1 }}</td>

                                <td class="px-4 py-3 text-sm text-gray-900 align-top">
                                    <div class="truncate">{{ $user->name }}</div>
                                </td>

                                <td class="px-4 py-3 text-sm text-gray-700 align-top">
                                    <div class="truncate">{{ $user->username }}</div>
                                </td>

                                {{-- Inline edit form per row --}}
                                <form method="POST" action="{{ route('users.update', $user) }}" class="contents">
                                    @csrf
                                    @method('PUT')

                                    <td class="px-4 py-3 text-sm align-top">
                                        <input
                                            type="text"
                                            name="productionID"
                                            value="{{ old('productionID', $user->productionID) }}"
                                            placeholder="-"
                                            class="w-full px-2 py-1.5 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        />
                                    </td>

                                    <td class="px-4 py-3 text-sm align-top">
                                        <input
                                            type="text"
                                            name="salesID"
                                            value="{{ old('salesID', $user->salesID) }}"
                                            placeholder="-"
                                            class="w-full px-2 py-1.5 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        />
                                    </td>

                                    <td class="px-4 py-3 text-sm align-top">
                                        <select
                                            name="role"
                                            class="w-full px-2 py-1.5 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        >
                                            @php
                                                $roles = ['Head','Admin','Production','Sales'];
                                            @endphp
                                            @foreach($roles as $r)
                                                <option value="{{ $r }}" @selected(old('role', $user->role) === $r)>
                                                    {{ $r }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700"
                                        >
                                            บันทึก
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
                                    ไม่พบบัญชีผู้ใช้
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
