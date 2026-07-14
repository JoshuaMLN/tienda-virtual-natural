@php
    $fieldPrefix = $fieldPrefix ?? 'register';
    $registerErrors = $errors->getBag('register');
    $showOldInput = $registerErrors->any();
@endphp

<form class="row g-3" method="POST" action="{{ route('register.store') }}" novalidate>
    @csrf

    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}-name">
            Nombre completo <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <input
            class="form-control {{ $registerErrors->has('name') ? 'is-invalid' : '' }}"
            id="{{ $fieldPrefix }}-name"
            name="name"
            type="text"
            value="{{ $showOldInput ? old('name') : '' }}"
            autocomplete="name"
            maxlength="120"
            required
        >
        @if($registerErrors->has('name'))
            <div class="invalid-feedback">{{ $registerErrors->first('name') }}</div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}-phone">
            Celular <span class="small text-muted">(opcional)</span>
        </label>
        <input
            class="form-control {{ $registerErrors->has('phone') ? 'is-invalid' : '' }}"
            id="{{ $fieldPrefix }}-phone"
            name="phone"
            type="tel"
            value="{{ $showOldInput ? old('phone') : '' }}"
            autocomplete="tel"
            inputmode="numeric"
            placeholder="987 654 321"
        >
        @if($registerErrors->has('phone'))
            <div class="invalid-feedback">{{ $registerErrors->first('phone') }}</div>
        @endif
    </div>

    <div class="col-12">
        <label class="form-label" for="{{ $fieldPrefix }}-email">
            Correo electronico <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <input
            class="form-control {{ $registerErrors->has('email') ? 'is-invalid' : '' }}"
            id="{{ $fieldPrefix }}-email"
            name="email"
            type="email"
            value="{{ $showOldInput ? old('email') : '' }}"
            autocomplete="email"
            required
        >
        @if($registerErrors->has('email'))
            <div class="invalid-feedback">{{ $registerErrors->first('email') }}</div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}-password">
            Contrasena <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <div class="input-group">
            <input
                class="form-control {{ $registerErrors->has('password') ? 'is-invalid' : '' }}"
                id="{{ $fieldPrefix }}-password"
                name="password"
                type="password"
                autocomplete="new-password"
                minlength="8"
                placeholder="Minimo 8 caracteres"
                required
            >
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-password-toggle="{{ $fieldPrefix }}-password"
                aria-label="Mostrar contrasena"
                aria-pressed="false"
            >
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        @if($registerErrors->has('password'))
            <div class="invalid-feedback d-block">{{ $registerErrors->first('password') }}</div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}-password-confirmation">
            Repite tu contrasena <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <div class="input-group">
            <input
                class="form-control"
                id="{{ $fieldPrefix }}-password-confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                minlength="8"
                required
            >
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-password-toggle="{{ $fieldPrefix }}-password-confirmation"
                aria-label="Mostrar contrasena"
                aria-pressed="false"
            >
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input
                class="form-check-input {{ $registerErrors->has('terms') ? 'is-invalid' : '' }}"
                id="{{ $fieldPrefix }}-terms"
                name="terms"
                type="checkbox"
                value="1"
                {{ $showOldInput && old('terms') ? 'checked' : '' }}
                required
            >
            <label class="form-check-label" for="{{ $fieldPrefix }}-terms">
                Acepto los <a class="text-vn-green fw-bold" href="{{ route('shop.terms') }}">terminos y condiciones</a>
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            @if($registerErrors->has('terms'))
                <div class="invalid-feedback">{{ $registerErrors->first('terms') }}</div>
            @endif
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-green w-100" type="submit">Crear mi cuenta</button>
    </div>
</form>
