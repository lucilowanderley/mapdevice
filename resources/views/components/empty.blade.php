@props(['icon' => 'list'])
<div class="empty">
    <x-icon :name="$icon" />
    <div>{{ $slot }}</div>
</div>
