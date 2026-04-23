<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Storage;

class SchoolSettingController extends Controller
{
    public function show() {
        $setting = SchoolSetting::first();
        if ($setting && $setting->logo_path) {
            $setting->logo_url = tenant_asset($setting->logo_path);
        }
        
        $response = $setting ? $setting->toArray() : [];
        $response['tenant_id'] = tenant('id');
        // organization_type is now stored in school_settings itself
        $response['organization_type'] = $response['organization_type'] ?? 'school';
        
        return response()->json($response);
    }

    public function update(Request $request) {
        $setting = SchoolSetting::first();
        if (!$setting) {
            $setting = new SchoolSetting();
            $setting->current_academic_year = date('Y') . '-' . (date('Y') + 1);
        }

        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'currency' => 'nullable|string|max:10',
            'fee_due_day' => 'nullable|integer|min:1|max:31',
            'late_fine_per_month' => 'nullable|numeric|min:0',
            'logo' => 'nullable|image|max:2048',
            'organization_type' => 'nullable|in:school,coaching,academy',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if exists from public disk
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $path = $request->file('logo')->store('branding', 'public');
            $setting->logo_path = $path;
        }

        $setting->school_name = $validated['school_name'];
        $setting->address = $validated['address'];
        $setting->phone = $validated['phone'];
        $setting->email = $validated['email'];
        $setting->currency = $validated['currency'] ?? 'PKR';
        $setting->fee_due_day = $validated['fee_due_day'] ?? 10;
        $setting->late_fine_per_month = $validated['late_fine_per_month'] ?? 0;
        
        $setting->organization_type = $validated['organization_type'] ?? 'school';
        $setting->save();

        // Return with full URL
        if ($setting->logo_path) {
            $setting->logo_url = tenant_asset($setting->logo_path);
        }

        return response()->json($setting);
    }
}
