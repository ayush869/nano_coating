@extends('layouts.staff')
@section('title', 'Users')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Add user</a>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach ($users as $u)
                <tr class="{{ $u->active ? '' : 'text-slate-400' }}">
                    <td class="font-medium">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ \App\Models\User::ROLES[$u->role] }}</td>
                    <td>{{ $u->phone }}</td>
                    <td>{{ $u->active ? 'Active' : 'Inactive' }}</td>
                    <td class="text-right"><a class="btn btn-sm" href="{{ route('admin.users.edit', $u) }}">Edit</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="mt-3 text-xs text-slate-500">Admin: rate card, rules, users, discount approval. Sales: customers and quotes. Technician: assigned jobs only.</p>
@endsection
