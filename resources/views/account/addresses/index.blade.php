@extends('layouts.account')

@section('title', 'Mis direcciones | VitaNatural')
@section('accountActive', 'addresses')

@section('accountContent')
@php
    $addressCount = $addresses->count();
    $reachedLimit = $addressCount >= $addressLimit;
    $statusMessages = [
        'address-created' => 'Direccion guardada correctamente.',
        'address-updated' => 'Direccion actualizada correctamente.',
        'address-default-updated' => 'Direccion predeterminada actualizada.',
        'address-deleted' => 'Direccion eliminada correctamente.',
        'address-deleted-default-promoted' => 'Direccion eliminada. '.session('promoted_address_label').' ahora es tu direccion predeterminada.',
    ];
    $statusMessage = $statusMessages[session('status')] ?? null;
@endphp

<div class="address-page-header d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title mb-1">Mis direcciones</h1>
        <p class="text-muted mb-0">Administra los lugares donde recibiras tus pedidos.</p>
    </div>
    <div class="text-sm-end">
        @if($reachedLimit)
            <button class="btn btn-vn" type="button" disabled aria-describedby="address-limit-message">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar direccion
            </button>
        @else
            <a class="btn btn-vn" href="{{ route('account.addresses.create') }}">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar direccion
            </a>
        @endif
        <div class="address-count mt-2">{{ $addressCount }} de {{ $addressLimit }} direcciones</div>
    </div>
</div>

@if($statusMessage)
    <div class="alert alert-success alert-dismissible fade show" role="status">
        <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ $statusMessage }}
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ session('warning') }}
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if($errors->getBag('defaultAddress')->any())
    <div class="alert alert-danger" role="alert">
        {{ $errors->getBag('defaultAddress')->first() }}
    </div>
@endif

@if($reachedLimit)
    <div class="alert alert-light border d-flex gap-2" id="address-limit-message" role="status">
        <i class="bi bi-info-circle text-vn flex-shrink-0" aria-hidden="true"></i>
        <span>Alcanzaste el limite de {{ $addressLimit }} direcciones. Elimina una para poder agregar otra.</span>
    </div>
@endif

@if($addresses->isEmpty())
    <div class="account-card account-empty-state p-5 text-center">
        <span class="account-empty-icon" aria-hidden="true">
            <i class="bi bi-geo-alt"></i>
        </span>
        <h2 class="h5 fw-black mt-3">Aun no tienes direcciones guardadas</h2>
        <p class="text-muted mx-auto mb-4">Agrega tu primera direccion para agilizar tus compras.</p>
        <a class="btn btn-vn" href="{{ route('account.addresses.create') }}">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar direccion
        </a>
    </div>
@else
    <form id="default-address-form" method="POST" action="{{ route('account.addresses.default') }}" data-default-address-form>
        @csrf
        @method('PATCH')
    </form>

    <div class="address-grid">
        @foreach($addresses as $address)
            <article class="account-card address-card {{ $address->is_default ? 'is-default' : '' }}">
                <div class="address-card-header">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <i class="bi bi-geo-alt-fill text-vn flex-shrink-0" aria-hidden="true"></i>
                        <h2 class="h6 fw-black mb-0 text-truncate">{{ $address->label }}</h2>
                    </div>
                    @if($address->is_default)
                        <span class="address-default-badge"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Predeterminada</span>
                    @endif
                </div>

                <div class="address-card-body">
                    <div class="address-recipient fw-bold">{{ $address->recipient_name }}</div>
                    <div class="text-muted small"><i class="bi bi-phone me-1" aria-hidden="true"></i>{{ $address->phone }}</div>
                    <p class="mb-1 mt-3">{{ $address->address_line }}</p>
                    <p class="text-muted small mb-0">{{ $address->district }}, {{ $address->province }}</p>
                    @if($address->reference)
                        <p class="address-reference small mb-0 mt-2"><span class="fw-bold">Referencia:</span> {{ $address->reference }}</p>
                    @endif
                </div>

                <div class="address-card-footer">
                    <label class="address-default-choice">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="address_id"
                            value="{{ $address->id }}"
                            form="default-address-form"
                            {{ $address->is_default ? 'checked' : '' }}
                            data-default-address-radio
                        >
                        <span>{{ $address->is_default ? 'Direccion predeterminada' : 'Usar como predeterminada' }}</span>
                    </label>
                    <div class="address-actions">
                        <a class="btn btn-sm btn-vn-outline" href="{{ route('account.addresses.edit', $address) }}" aria-label="Editar {{ $address->label }}">
                            <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                        </a>
                        <button
                            class="btn btn-sm btn-outline-danger"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteAddressModal"
                            data-address-delete
                            data-address-action="{{ route('account.addresses.destroy', $address) }}"
                            data-address-label="{{ $address->label }}"
                            data-address-default="{{ $address->is_default ? '1' : '0' }}"
                        >
                            <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                        </button>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <noscript>
        <button class="btn btn-vn-outline mt-3" type="submit" form="default-address-form">Guardar direccion predeterminada</button>
    </noscript>
@endif

<div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-black" id="deleteAddressModalLabel">Eliminar direccion</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">¿Seguro que deseas eliminar <strong data-address-delete-label></strong>?</p>
                <p class="alert alert-warning small mb-0 d-none" data-address-delete-default-note>
                    Esta es tu direccion predeterminada. Al eliminarla, seleccionaremos otra direccion guardada como predeterminada.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-vn-outline" type="button" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="" data-address-delete-form>
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-trash" aria-hidden="true"></i> Eliminar direccion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
