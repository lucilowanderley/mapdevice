<?php

namespace App\Http\Controllers;

use App\Discovery\DeviceProber;
use App\Models\Device;
use App\Models\DeviceDiscovery;
use Illuminate\Http\Request;

/** Sonda de descoberta: identifica fabricante/SO do equipamento (só leitura) e ajuda a preencher o cadastro. */
class DiscoveryController extends Controller
{
    public function __construct(private readonly DeviceProber $prober) {}

    public function show(Device $device)
    {
        return view('discovery.show', [
            'device' => $device,
            'latest' => $device->discoveries()->latest()->first(),
            'history' => $device->discoveries()->latest()->limit(10)->get(),
        ]);
    }

    /** Roda a sonda e salva o resultado (só leitura; nunca altera o equipamento). */
    public function run(Request $request, Device $device)
    {
        $result = $this->prober->probe($device);
        DeviceDiscovery::fromResult($device, $result, $request->user()?->id);

        $message = $result->hasGuess()
            ? "Descoberta concluída: parece {$result->manufacturer} ({$result->os}), confiança {$result->confidence}%."
            : 'Descoberta concluída, mas não deu para identificar o fabricante pelas pistas coletadas.';

        return redirect()->route('discovery.show', $device)->with('success', $message);
    }

    /** Aplica o palpite (fabricante/SO/acesso) ao cadastro do equipamento. Só administrador. */
    public function apply(Request $request, Device $device, DeviceDiscovery $discovery)
    {
        abort_unless($discovery->device_id === $device->id, 404);

        $device->fill(array_filter([
            'manufacturer' => $discovery->manufacturer,
            'os' => $discovery->os,
            'access_method' => $discovery->access_method ?: $device->access_method,
        ], fn ($v) => $v !== null && $v !== ''))->save();

        return redirect()->route('discovery.show', $device)->with('success', 'Cadastro do equipamento atualizado com o resultado da descoberta.');
    }
}
