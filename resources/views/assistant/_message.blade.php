@php($isUser = $message->role === 'user')
<div class="msg {{ $isUser ? 'user' : 'ai' }} {{ $message->is_error ? 'err' : '' }}">
    <div class="who">
        {{ $isUser ? 'Você' : ($message->provider_name ?: 'Assistente') }}
        @unless($isUser) @if($message->model) <span class="hint">· {{ $message->model }}</span> @endif @endunless
    </div>
    <div class="body">
        @if($isUser)
            {!! nl2br(e($message->content)) !!}
        @else
            {!! $message->html !!}
        @endif
    </div>
</div>
