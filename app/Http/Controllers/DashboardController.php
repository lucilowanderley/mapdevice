<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\Device;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'deviceCount' => Device::count(),
            'activeDevices' => Device::where('active', true)->count(),
            'providerCount' => AiProvider::active()->count(),
            'defaultProvider' => AiProvider::default(),
            'recentDevices' => Device::latest()->limit(8)->get(),
        ]);
    }
}
