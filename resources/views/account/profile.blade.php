@extends('layouts.account')

@section('title', 'Mi perfil | VitaNatural')
@section('accountActive', 'profile')

@section('accountContent')
@php($profileErrors = $errors->getBag('profile'))

<div class="mb-4">
    <h1 class="section-title mb-1">Mi perfil</h1>
    <p class="text-muted mb-0">Administra tus datos personales y tu foto de perfil.</p>
</div>

@if(session('status') === 'profile-updated')
    <div class="alert alert-success" role="status">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
        Tus datos se actualizaron correctamente.
    </div>
@elseif(session('status') === 'profile-updated-verification-required')
    <div class="alert alert-warning" role="status">
        <i class="bi bi-envelope-exclamation me-1" aria-hidden="true"></i>
        Actualizamos tus datos y enviamos un enlace de verificacion a tu nuevo correo.
    </div>
@endif

<form
    class="account-card p-4"
    method="POST"
    action="{{ route('account.profile.update') }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @method('PATCH')

    <div class="profile-summary d-flex flex-wrap align-items-center gap-3 pb-4 mb-4">
        @if($user->avatar_url)
            <img
                class="profile-avatar-display"
                src="{{ $user->avatar_url }}"
                alt="Avatar de {{ $user->name }}"
                width="88"
                height="88"
            >
        @else
            <span class="profile-avatar-display profile-avatar-fallback" aria-label="Iniciales de {{ $user->name }}">
                {{ $user->initials }}
            </span>
        @endif

        <div class="flex-grow-1">
            <h2 class="h5 fw-black mb-1">{{ $user->name }}</h2>
            <p class="text-muted mb-2">{{ $user->email }}</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($user->hasVerifiedEmail())
                    <span class="badge text-bg-success">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Correo verificado
                    </span>
                @else
                    <span class="badge text-bg-warning">
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Correo pendiente
                    </span>
                @endif
                <span class="small text-muted">
                    Cliente desde {{ $user->created_at->format('d/m/Y') }}
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="profile-data-title">
            <h2 class="h5 fw-black mb-1" id="profile-data-title">Datos personales</h2>
            <p class="text-muted mb-4">Esta informacion se utilizara para identificar tu cuenta.</p>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="profile-name">
                        Nombre completo <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control {{ $profileErrors->has('name') ? 'is-invalid' : '' }}"
                        id="profile-name"
                        name="name"
                        type="text"
                        value="{{ old('name', $user->name) }}"
                        maxlength="120"
                        autocomplete="name"
                        required
                    >
                    @if($profileErrors->has('name'))
                        <div class="invalid-feedback">{{ $profileErrors->first('name') }}</div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="profile-email">
                        Correo electronico <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="{{ $googleAccount ? 'input-group' : '' }}">
                        <input
                            class="form-control {{ $profileErrors->has('email') ? 'is-invalid' : '' }}"
                            id="profile-email"
                            name="email"
                            type="email"
                            value="{{ old('email', $user->email) }}"
                            maxlength="255"
                            autocomplete="email"
                            @if($googleAccount)
                                readonly
                                aria-describedby="profile-email-google-note"
                                data-google-email-locked
                            @endif
                            required
                        >
                        @if($googleAccount)
                            <span
                                class="input-group-text text-vn-green"
                                data-bs-toggle="tooltip"
                                data-bs-title="Desvincula Google para habilitar este campo"
                                aria-label="Correo bloqueado por vinculacion con Google"
                            >
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            </span>
                        @endif
                    </div>
                    @if($profileErrors->has('email'))
                        <div class="invalid-feedback d-block">{{ $profileErrors->first('email') }}</div>
                    @endif
                    @if($googleAccount)
                        <div class="form-text profile-field-note" id="profile-email-google-note">
                            Para cambiarlo, primero
                            <a class="text-vn-green fw-bold" href="{{ route('account.security') }}">desvincula Google desde Seguridad</a>.
                            @if($user->password === null)
                                Antes deberas definir una contrasena.
                            @endif
                        </div>
                    @else
                        <div class="form-text profile-field-note">
                            Si lo cambias, deberas verificar nuevamente tu correo.
                        </div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="profile-phone">Celular</label>
                    <input
                        class="form-control {{ $profileErrors->has('phone') ? 'is-invalid' : '' }}"
                        id="profile-phone"
                        name="phone"
                        type="tel"
                        value="{{ old('phone', $user->phone) }}"
                        inputmode="numeric"
                        maxlength="9"
                        autocomplete="tel"
                        placeholder="987654321"
                    >
                    @if($profileErrors->has('phone'))
                        <div class="invalid-feedback">{{ $profileErrors->first('phone') }}</div>
                    @endif
                    <div class="form-text profile-field-note">Celular peruano de 9 digitos.</div>
                </div>
            </div>
        </section>

        <section class="col-lg-5 profile-avatar-panel" aria-labelledby="profile-avatar-title">
            <h2 class="h5 fw-black mb-1" id="profile-avatar-title">Foto de perfil</h2>
            <p class="text-muted mb-3">Usa una imagen cuadrada de hasta 4 MB.</p>

            <div
                class="image-cropper"
                data-image-cropper
                data-cropper-width="720"
                data-cropper-height="720"
                data-cropper-aspect="1"
                @if($user->avatar_url) data-cropper-preview-url="{{ $user->avatar_url }}" @endif
            >
                <label class="form-label" for="profile-avatar">Subir imagen</label>
                <input
                    class="form-control {{ $profileErrors->has('avatar') || $profileErrors->has('cropped_avatar') ? 'is-invalid' : '' }}"
                    id="profile-avatar"
                    name="avatar"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    data-cropper-input
                >
                <input name="cropped_avatar" type="hidden" data-cropper-output>
                <input name="remove_avatar" type="hidden" value="0" data-cropper-remove-input>

                @if($profileErrors->has('avatar') || $profileErrors->has('cropped_avatar'))
                    <div class="invalid-feedback d-block">
                        {{ $profileErrors->first('avatar') ?: $profileErrors->first('cropped_avatar') }}
                    </div>
                @endif

                <div class="cropper-frame cropper-frame-square mt-3">
                    <div class="cropper-placeholder" data-cropper-placeholder>
                        <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
                        <span>Sube una imagen para ver su vista previa</span>
                    </div>
                    <canvas data-cropper-canvas width="720" height="720"></canvas>
                </div>

                <label class="form-label small mt-3" for="profile-avatar-zoom">Zoom</label>
                <input
                    class="form-range"
                    id="profile-avatar-zoom"
                    type="range"
                    min="1"
                    max="2"
                    value="1"
                    step="0.05"
                    data-cropper-zoom
                >

                @if($user->avatar_path)
                    <div data-current-media>
                        <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-cropper-remove>
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Quitar avatar
                        </button>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div class="d-flex justify-content-end pt-4 mt-4 border-top">
        <button class="btn btn-green" type="submit">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar cambios
        </button>
    </div>
</form>
@endsection
