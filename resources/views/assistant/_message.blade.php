@if ($message->role === 'user')
    <div class="msg msg-user" id="m{{ $message->id }}">
        <div class="msg-bubble pre">{{ $message->content }}</div>
    </div>
@elseif ($message->is_error)
    <div class="msg msg-ai msg-error" id="m{{ $message->id }}">
        <div class="msg-avatar"><x-icon name="alert" class="sm" /></div>
        <div class="msg-body">
            <div class="msg-bubble">
                <strong>Não foi possível obter a resposta.</strong>
                <div>{{ $message->content }}</div>
            </div>
            <div class="msg-meta">
                <form method="post" action="{{ route('assistant.retry', $message->ai_conversation_id) }}">@csrf<button class="btn btn-xs"><x-icon name="history" class="sm" /> Tentar novamente</button></form>
                @if(auth()->user()->isAdmin())<a class="btn btn-xs btn-ghost" href="{{ route('ai-providers.index') }}"><x-icon name="plug" class="sm" /> Provedores</a>@endif
            </div>
        </div>
    </div>
@else
    <div class="msg msg-ai" id="m{{ $message->id }}">
        <div class="msg-avatar"><x-icon name="sparkles" class="sm" /></div>
        <div class="msg-body">
            <div class="msg-bubble markdown">{!! $message->html !!}</div>
            <textarea hidden data-copy-raw>{{ $message->content }}</textarea>
            <div class="msg-meta">
                <span class="faint small">{{ $message->provider_name }}@if($message->model) · {{ $message->model }}@endif @if($message->output_tokens) · {{ number_format(($message->input_tokens ?? 0) + $message->output_tokens, 0, ',', '.') }} tokens @endif</span>
                <button type="button" class="btn btn-xs btn-ghost" data-copy><x-icon name="check-check" class="sm" /> Copiar</button>
            </div>
        </div>
    </div>
@endif
