@props(['status'])

<span class="customer-order-status is-{{ $status->tone() }}">
    <i class="bi {{ $status->icon() }}" aria-hidden="true"></i>
    {{ $status->label() }}
</span>
