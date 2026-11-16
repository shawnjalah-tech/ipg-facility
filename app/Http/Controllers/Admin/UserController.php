<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $validRoles = ['admin', 'staff'];

        $query = User::withCount('bookings')
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if (in_array($role, $validRoles, true)) {
            $query->where('role', $role);
        } else {
            $role = null;
        }

        $request->validate(['q' => 'nullable|string|max:200']);
        if ($request->filled('q')) {
            $search = $request->query('q');
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('employee_id', 'like', '%'.$search.'%'));
        }
        $users = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
            'staff' => User::where('role', 'staff')->count(),
        ];

        return view('admin.users.index', compact('users', 'role', 'counts'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User {$user->name} created successfully.");
    }

    public function show(User $user)
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(Request $request, User $user)
    {
        $isSelf = $request->user()->id === $user->id;

        return view('admin.users.edit', compact('user', 'isSelf'));
    }

    public function update(Request $request, User $user)
    {
        $isSelf = $request->user()->id === $user->id;
        $isLastAdmin = $user->role === 'admin' && User::where('role', 'admin')->count() <= 1;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $profile = array_diff_key($data, ['password' => true]);
        if ($isSelf && $data['role'] !== 'admin') {
            return back()->withInput($profile)->with('error',
                'You cannot remove your own admin role. Ask another admin to do it.');
        }

        if ($isLastAdmin && $data['role'] !== 'admin') {
            return back()->withInput($profile)->with('error',
                'This is the last admin account. Promote another user to admin before changing this one.');
        }

        // --- Apply changes ---
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->employee_id = $data['employee_id'] ?? null;
        $user->role = $data['role'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User {$user->name} updated successfully.");
    }

    public function destroy(Request $request, User $user)
    {
        // --- Safety guards ---
        if ($request->user()->id === $user->id) {
            return back()->with('error',
                'You cannot delete your own account.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error',
                'Cannot delete the last admin account. Promote another user to admin first.');
        }

        // Keep booking history and give a clear error before the foreign-key check.
        $activeBookings = Booking::where('user_id', $user->id)
            ->count();

        if ($activeBookings > 0) {
            return back()->with('error',
                "Cannot delete {$user->name} — they have {$activeBookings} recorded ".
                Str::plural('booking', $activeBookings).'. '.
                'Booking history must be retained.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User {$name} deleted.");
    }
}
