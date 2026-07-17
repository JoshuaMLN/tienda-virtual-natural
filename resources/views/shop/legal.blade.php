@extends('layouts.shop')

@section('title', $legalDocument->title.' | VitaNatural')

@section('content')
<section class="legal-page py-5">
    <div class="container">
        @if($isDemoMode)
            <div class="alert alert-warning mb-4" role="alert">
                <i class="bi bi-cone-striped me-2"></i>
                Este sitio funciona en modo demostrativo. No procesa ventas ni pagos reales.
            </div>
        @endif

        <div class="row g-4 g-lg-5 align-items-start">
            <aside class="col-lg-3">
                <nav class="legal-nav" aria-label="Documentos legales">
                    <a class="{{ $legalDocument->type === \App\Enums\LegalDocumentType::Terms ? 'active' : '' }}" href="{{ route('shop.terms') }}">
                        <i class="bi bi-file-earmark-text"></i> Terminos y condiciones
                    </a>
                    <a class="{{ $legalDocument->type === \App\Enums\LegalDocumentType::Privacy ? 'active' : '' }}" href="{{ route('shop.privacy') }}">
                        <i class="bi bi-shield-lock"></i> Politica de privacidad
                    </a>
                </nav>
            </aside>

            <article class="col-lg-9 legal-document">
                <header class="border-bottom pb-3 mb-4">
                    <h1 class="section-title mb-2">{{ $legalDocument->title }}</h1>
                    <p class="text-muted small mb-0">
                        Version {{ $legalDocument->version }} &middot;
                        Publicada el {{ $legalDocument->published_at->format('d/m/Y') }}
                    </p>
                </header>
                <div class="legal-document-content">{!! $legalBody !!}</div>
            </article>
        </div>
    </div>
</section>
@endsection
