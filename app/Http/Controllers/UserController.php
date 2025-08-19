<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Only Head can access
        abort_unless(optional($request->user())->role === 'Head', 403);

        $q = trim($request->get('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('role', 'like', "%{$q}%");
                });
            })
            ->orderByRaw("FIELD(role, 'Head', 'Admin', 'Production', 'Sales')")
            ->orderBy('name') // 👈 Secondary sort by name within same role
            ->get();

        return view('users.index', compact('users', 'q'));
    }
    public function update(Request $request, User $user)
    {
        // Only Head can edit users
        abort_unless(optional($request->user())->role === 'Head', 403);

        $data = $request->validate([
            'productionID' => ['nullable','string'],
            'salesID'      => ['nullable','string'],
            'role'         => ['required','in:Head,Admin,Production,Sales'],
        ]);

        $old = $user->only(['productionID','salesID','role']);
        $newRole = $data['role'];
        $oldRole = $user->role;

        // 🔒 Business rules
        // 1) Head cannot change role (but may edit IDs)
        if ($oldRole === 'Head' && $newRole !== 'Head') {
            return back()->with('role_error', 'ไม่สามารถเปลี่ยนบทบาทของผู้ใช้ที่เป็น Head ได้');
        }

        // 2) No one can be changed TO Head (except if they already are Head)
        if ($oldRole !== 'Head' && $newRole === 'Head') {
            return back()->with('role_error', 'ไม่อนุญาตให้เปลี่ยนบทบาทเป็น Head');
        }

        // 3) Move to Production requires productionID
        if ($newRole === 'Production' && blank($data['productionID'])) {
            return back()->with('role_error', 'กรุณากรอก Production ID ก่อน');
        }

        // 4) Move to Sales requires salesID
        if ($newRole === 'Sales' && blank($data['salesID'])) {
            return back()->with('role_error', 'กรุณากรอก Sales ID ก่อน');
        }

        // Save
        $user->fill($data)->save();

        // Build changes diff for success SweetAlert
        $changed = [];
        foreach (['productionID','salesID','role'] as $field) {
            if ($old[$field] !== $user->{$field}) {
                $changed[$field] = ['old' => $old[$field], 'new' => $user->{$field}];
            }
        }

        return back()->with('changes', [
            'name'    => $user->name,
            'changes' => $changed,
        ]);
    }

    public function store(Request $request)
    {
        // Only Head can create users
        abort_unless(optional($request->user())->role === 'Head', 403);

        $validator = Validator::make($request->all(), [
            'name'         => ['required','string','max:255'],
            'username'     => ['required','string','max:255','unique:users,username'],
            'password'     => ['required','string','min:6'],
            'role'         => ['required','in:Head,Admin,Production,Sales'],
            'productionID' => ['nullable','string','max:255'],
            'salesID'      => ['nullable','string','max:255'],
        ]);

        // Business rules as part of the validator (no JSON here)
        $validator->after(function ($v) use ($request) {
            $role = $request->input('role');

            if ($role === 'Production' && blank($request->input('productionID'))) {
                $v->errors()->add('productionID', 'กรุณากรอก Production ID ก่อน');
            }

            if ($role === 'Sales' && blank($request->input('salesID'))) {
                $v->errors()->add('salesID', 'กรุณากรอก Sales ID ก่อน');
            }
        });

        if ($validator->fails()) {
            // Re-open the modal and keep input + errors
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('show_create_modal', true);
        }

        $data = $validator->validated();

        $user = \App\Models\User::create([
            'name'         => $data['name'],
            'username'     => $data['username'],
            'password'     => Hash::make($data['password']),
            'role'         => $data['role'],
            'productionID' => $data['productionID'] ?? null,
            'salesID'      => $data['salesID'] ?? null,
        ]);

        return redirect()
            ->route('users.index')
            ->with('created', [
                'id'       => $user->id,
                'name'     => $user->name,
                'role'     => $user->role,
                'username' => $user->username,
            ]);
    }

}
