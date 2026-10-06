<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Service categories shown on the website. */
class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index', [
            'services' => Service::ordered()->withCount('products')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.services.form', ['service' => new Service(['active' => true, 'sort' => (Service::max('sort') ?? 0) + 10])]);
    }

    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));

        return redirect()->route('admin.services.index')->with('ok', "Service “{$service->name}” added.");
    }

    public function edit(Service $service)
    {
        return view('admin.services.form', ['service' => $service, 'imageUrl' => Media::url($service->image_path)]);
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('admin.services.index')->with('ok', "Service “{$service->name}” saved.");
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('services')->ignore($service)],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'icon' => ['nullable', 'string', 'max:16'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name'], $service);
        $data['active'] = $request->boolean('active');
        $data['sort'] ??= 0;

        if ($request->hasFile('image')) {
            if ($service?->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $data['image_path'] = $request->file('image')->store('services', 'public');
        }
        unset($data['image']);

        return $data;
    }

    private function uniqueSlug(string $name, ?Service $service): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        for ($i = 2; Service::where('slug', $slug)->when($service, fn ($q) => $q->whereKeyNot($service->id))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
