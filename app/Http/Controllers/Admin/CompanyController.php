<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function edit()
    {
        return view('admin.company', ['company' => Company::current()]);
    }

    public function update(Request $request)
    {
        $company = Company::current();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'size:15'],
            'address' => ['nullable', 'string', 'max:1000'],
            'state_code' => ['required', Rule::in(array_keys(config('gst.states')))],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'signatory' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logo', 'public');
        }
        unset($data['logo']);

        $company->update($data);

        return back()->with('ok', 'Company profile saved.');
    }
}
