<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Device;
use App\Support\Ai\AiException;
use App\Support\Ai\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function __construct(private Assistant $assistant) {}

    /** Nova conversa (opcionalmente já vinculada a um equipamento). */
    public function index(Request $request): View
    {
        $draft = new AiConversation(['device_id' => Device::find($request->integer('equipamento'))?->id]);
        $draft->setRelation('device', $draft->device_id ? Device::find($draft->device_id) : null);

        return view('assistant.index', $this->shared($request) + ['conversation' => $draft, 'messages' => collect()]);
    }

    public function show(Request $request, AiConversation $conversation): View
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->load(['device', 'provider']);

        return view('assistant.index', $this->shared($request) + [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:30000'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'ai_provider_id' => ['nullable', 'integer', Rule::exists('ai_providers', 'id')->where('active', true)],
        ]);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'device_id' => $data['device_id'] ?? null,
            'ai_provider_id' => $data['ai_provider_id'] ?? AiProvider::default()?->id,
            'title' => Assistant::titleFrom($data['message']),
        ]);

        return $this->ask($request, $conversation, $data['message'], true);
    }

    public function send(Request $request, AiConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $data = $request->validate(['message' => ['required', 'string', 'max:30000']]);

        return $this->ask($request, $conversation, $data['message']);
    }

    /** Troca o provedor/modelo usado por uma conversa. */
    public function update(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);
        $data = $request->validate([
            'ai_provider_id' => ['nullable', 'integer', Rule::exists('ai_providers', 'id')->where('active', true)],
            'title' => ['nullable', 'string', 'max:150'],
        ]);
        $conversation->update(array_filter($data, fn ($v) => $v !== null));

        return redirect()->route('assistant.show', $conversation);
    }

    /** Tenta de novo a última pergunta (após um erro do provedor, por exemplo). */
    public function retry(Request $request, AiConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->messages()->where('is_error', true)->delete();
        $last = $conversation->messages()->get()->last();
        if ($last && $last->role === 'assistant') {
            $last->delete();
        }

        return $this->respond($request, $conversation);
    }

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return redirect()->route('assistant.index')->with('success', 'Conversa excluída.');
    }

    private function ask(Request $request, AiConversation $conversation, string $text, bool $isNew = false): RedirectResponse|JsonResponse
    {
        $conversation->messages()->create(['role' => 'user', 'content' => $text]);

        return $this->respond($request, $conversation, $isNew);
    }

    private function respond(Request $request, AiConversation $conversation, bool $isNew = false): RedirectResponse|JsonResponse
    {
        // Modelos grandes podem levar mais de 30 s (limite padrão do PHP no Laragon).
        @set_time_limit((int) config('services.ai.timeout', 180) + 30);

        try {
            $reply = $this->assistant->reply($conversation);
        } catch (AiException $e) {
            $reply = $conversation->messages()->create(['role' => 'assistant', 'content' => $e->getMessage(), 'is_error' => true]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $isNew ? route('assistant.show', $conversation) : null,
                'html' => view('assistant._message', ['message' => $reply])->render(),
            ]);
        }

        return redirect()->to(route('assistant.show', $conversation).'#fim');
    }

    private function shared(Request $request): array
    {
        return [
            'conversations' => AiConversation::where('user_id', $request->user()->id)
                ->with(['device:id,name,manufacturer,model'])
                ->latest('updated_at')->limit(60)->get(),
            'devices' => Device::orderBy('name')->get(['id', 'name', 'manufacturer', 'model']),
            'providers' => AiProvider::active()->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultProvider' => AiProvider::default(),
        ];
    }

    private function authorizeOwner(Request $request, ?AiConversation $conversation): void
    {
        abort_unless($conversation && $conversation->user_id === $request->user()->id, 404);
    }
}
