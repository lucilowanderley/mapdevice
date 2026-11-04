<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cadastro dos equipamentos da rede gerenciados pelo provedor. */
class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = Device::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('manufacturer', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('host', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('devices.index', compact('devices'));
    }

    public function create(): View
    {
        return view('devices.form', ['device' => new Device(['access_method' => 'ssh', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Device::create($this->validated($request));

        return redirect()->route('devices.index')->with('success', 'Equipamento cadastrado.');
    }

    public function edit(Device $device): View
    {
        return view('devices.form', compact('device'));
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $device->update($this->validated($request, $device));

        return redirect()->route('devices.index')->with('success', 'Equipamento atualizado.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Equipamento excluído.');
    }

    private function validated(Request $request, ?Device $device = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'module' => ['nullable', Rule::in(array_keys(Device::MODULES))],
            'manufacturer' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'firmware' => ['nullable', 'string', 'max:60'],
            'os' => ['nullable', 'string', 'max:80'],
            'host' => ['nullable', 'string', 'max:255'],
            'access_method' => ['required', Rule::in(array_keys(Device::ACCESS_METHODS))],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'snmp_community' => ['nullable', 'string', 'max:120'],
            'clear_password' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        // A senha só é trocada quando uma nova é digitada (o formulário nunca exibe a atual).
        if ($request->boolean('clear_password')) {
            $data['password'] = null;
        } elseif (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if (blank($data['snmp_community'] ?? null)) {
            unset($data['snmp_community']);
        }
        unset($data['clear_password']);

        return $data;
    }
}
