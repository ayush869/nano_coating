@extends('layouts.staff')
@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('content')
<h1 class="mb-4 text-xl font-semibold">{{ $user->exists ? 'Edit '.$user->name : 'Add user' }}</h1>

<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card card-body max-w-xl space-y-4">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Name *</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label class="label">Email *</label><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div>
            <label class="label">Role *</label>
            <select class="input" name="role">
                @foreach (\App\Models\User::ROLES as $k => $v)
                    <option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Phone</label><input class="input" name="phone" value="{{ old('phone', $user->phone) }}"></div>
        <div>
            <label class="label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password *' }}</label>
            <input class="input" type="password" name="password" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
        </div>
        <label class="flex items-center gap-2 self-end pb-2 text-sm">
            <input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true))> Active
        </label>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.users.index') }}" class="btn">Cancel</a>
    </div>
</form>
@endsection
