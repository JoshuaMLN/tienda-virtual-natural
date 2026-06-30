@extends('layouts.shop')

@section('title', 'Contacto | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <h1 class="section-title">Estamos aqui para ayudarte</h1>
            <p class="text-muted">Tienes dudas o necesitas asesoria? Nuestro equipo esta listo para ayudarte a vivir tu mejor version.</p>
            <div class="d-grid gap-3">
                <div class="checkout-card p-3 d-flex align-items-center gap-3">
                    <i class="bi bi-whatsapp fs-2 text-vn-green"></i>
                    <div><strong>WhatsApp</strong><br><span class="small">987 654 321</span></div>
                </div>
                <div class="checkout-card p-3 d-flex align-items-center gap-3">
                    <i class="bi bi-envelope fs-2 text-vn-green"></i>
                    <div><strong>Correo electronico</strong><br><span class="small">hola@vitanatural.pe</span></div>
                </div>
                <div class="checkout-card p-3 d-flex align-items-center gap-3">
                    <i class="bi bi-telephone fs-2 text-vn-green"></i>
                    <div><strong>Telefono</strong><br><span class="small">(01) 123 4567</span></div>
                </div>
                <div class="checkout-card p-3 d-flex align-items-center gap-3">
                    <i class="bi bi-clock fs-2 text-vn-green"></i>
                    <div><strong>Horario de atencion</strong><br><span class="small">Lun a Vie: 9:00 am - 6:00 pm</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="checkout-card p-4 h-100">
                <h2 class="h5 fw-black mb-3">Envianos un mensaje</h2>
                <div class="d-grid gap-3">
                    <input class="form-control" type="text" placeholder="Tu nombre">
                    <input class="form-control" type="email" placeholder="tu@email.com">
                    <select class="form-select">
                        <option>Selecciona un asunto</option>
                        <option>Pedido</option>
                        <option>Producto</option>
                        <option>Soporte</option>
                    </select>
                    <textarea class="form-control" rows="5" placeholder="Escribe tu mensaje aqui..."></textarea>
                    <button class="btn btn-green" type="button">Enviar mensaje</button>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="map-placeholder rounded-2 border mb-3"></div>
            <div class="promo-tile p-4">
                <h3 class="h5 fw-black">Bienestar que se nota</h3>
                <p class="small text-muted mb-0">En VitaNatural creemos en el poder de lo natural para transformar tu vida.</p>
            </div>
        </div>
    </div>
</section>
@endsection
