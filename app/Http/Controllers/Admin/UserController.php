<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::orderBy('role')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => User::SALES, 'active' => true])]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('admin.users.index')->with('ok', 'User added.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is($request->user()) && ($data['role'] !== User::ADMIN || ! $data['active'])) {
            return back()->with('error', 'You cannot remove your own admin access.');
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('ok', 'User saved.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
