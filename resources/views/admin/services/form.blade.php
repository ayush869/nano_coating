@extends('layouts.staff')
@section('title', $service->exists ? 'Edit service' : 'Add service')

@section('content')
<h1 class="mb-4 text-xl font-semibold">{{ $service->exists ? 'Edit '.$service->name : 'Add service category' }}</h1>

<form method="post" enctype="multipart/form-data" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="card card-body max-w-2xl space-y-4">
    @csrf
    @if ($service->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-[5rem_1fr]">
        <div><label class="label">Icon</label><input class="input text-center text-xl" name="icon" maxlength="16" value="{{ old('icon', $service->icon) }}" placeholder="🛋️"></div>
        <div><label class="label">Name *</label><input class="input" name="name" value="{{ old('name', $service->name) }}" required></div>
    </div>
    <div><label class="label">Short summary (cards on the website)</label><input class="input" name="summary" maxlength="255" value="{{ old('summary', $service->summary) }}"></div>
    <div><label class="label">Description (service page)</label><textarea class="input" name="description" rows="4">{{ old('description', $service->description) }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Web address</label><input class="input" name="slug" placeholder="auto from name" value="{{ old('slug', $service->slug) }}"></div>
        <div><label class="label">Sort order</label><input class="input" type="number" name="sort" min="0" value="{{ old('sort', $service->sort) }}"></div>
    </div>
    <div>
        <label class="label">Image</label>
        <div class="flex items-center gap-3">
            @if (! empty($imageUrl))<img src="{{ $imageUrl }}" class="h-14 rounded border" alt="">@endif
            <input type="file" name="image" accept="image/*" class="text-sm">
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $service->active))> Show on the website</label>
    <div class="flex gap-2">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.services.index') }}" class="btn">Cancel</a>
    </div>
</form>
@endsection
