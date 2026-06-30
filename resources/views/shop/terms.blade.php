@extends('layouts.shop')

@section('title', 'Terminos y condiciones | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="row g-4">
        <aside class="col-lg-3">
            <div class="checkout-card p-2 sticky-lg-top" style="top: 120px;">
                @foreach(['Compras', 'Envios', 'Cambios y devoluciones', 'Privacidad y proteccion de datos', 'Pagos con Culqi'] as $item)
                    <a class="d-flex justify-content-between align-items-center p-3 border-bottom small fw-bold" href="#terms">{{ $item }} <i class="bi bi-chevron-right"></i></a>
                @endforeach
            </div>
        </aside>
        <div class="col-lg-9">
            <div class="accordion d-grid gap-3" id="terms">
                @foreach([
                    'Compras' => 'Al realizar una compra en VitaNatural, el cliente acepta nuestras condiciones generales. Nos reservamos el derecho de modificar precios, promociones y disponibilidad de productos sin previo aviso.',
                    'Envios' => 'Los tiempos de entrega son referenciales y dependen de la zona de reparto. El envio gratis aplica desde el monto minimo vigente.',
                    'Cambios y devoluciones' => 'Aceptamos cambios por fallas o errores de despacho. El producto debe conservar su empaque original.',
                    'Privacidad y proteccion de datos' => 'Usamos tus datos solo para procesar pedidos, atencion al cliente y comunicaciones autorizadas.',
                    'Pagos con Culqi' => 'El pago se procesa mediante Culqi. VitaNatural no almacena datos sensibles de tarjetas. La orden se confirma cuando el backend recibe validacion del proveedor.'
                ] as $title => $copy)
                    <div class="accordion-item checkout-card overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#term{{ $loop->index }}" type="button">
                                {{ $loop->iteration }}. {{ $title }}
                            </button>
                        </h2>
                        <div id="term{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#terms">
                            <div class="accordion-body">{{ $copy }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="mt-5">
        <x-shop.trust-badges />
    </div>
</section>
@endsection
