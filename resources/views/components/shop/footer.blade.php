<footer class="footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <a class="brand-mark mb-3" href="{{ route('shop.index') }}">
                    <span class="brand-leaf"><i class="bi bi-flower1"></i></span>
                    <span>VitaNatural <span class="brand-subtitle">Bienestar que se nota</span></span>
                </a>
                <p class="small text-muted mb-0">Productos naturales seleccionados para tu bienestar.</p>
            </div>
            <div class="col-lg-2 col-md-6">
                <h6>Compra</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="{{ route('shop.catalog') }}">Todas las categorias</a></li>
                    <li><a href="{{ route('shop.catalog', ['oferta' => 1]) }}">Ofertas</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6">
                <h6>Ayuda</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="{{ route('shop.contact') }}">Centro de ayuda</a></li>
                    <li><a href="{{ route('shop.terms') }}">Envios y entregas</a></li>
                    <li><a href="{{ route('shop.terms') }}">Cambios y devoluciones</a></li>
                    <li><a href="{{ route('shop.terms') }}">Preguntas frecuentes</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6">
                @guest
                    <h6>Cuenta</h6>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a href="{{ route('login') }}">Iniciar sesion</a></li>
                        <li><a href="{{ route('register') }}">Crear cuenta</a></li>
                    </ul>
                @else
                    <h6>Mi cuenta</h6>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a href="{{ route('account.profile') }}">Mi perfil</a></li>
                        <li><a href="{{ route('account.orders') }}">Mis pedidos</a></li>
                        <li><a href="{{ route('account.addresses') }}">Mis direcciones</a></li>
                        <li><a href="{{ route('account.security') }}">Seguridad</a></li>
                        @unless(auth()->user()->hasVerifiedEmail())
                            <li><a href="{{ route('verification.notice') }}">Verificar correo</a></li>
                        @endunless
                    </ul>
                @endguest
            </div>
            <div class="col-lg-3 col-md-6">
                <h6>Contactanos</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><i class="bi bi-whatsapp text-vn-green"></i> WhatsApp 987 654 321</li>
                    <li><i class="bi bi-envelope text-vn-green"></i> hola@vitanatural.pe</li>
                    <li>Lun a Vie: 9:00 am - 6:00 pm</li>
                    <li>Sabado: 9:00 am - 1:00 pm</li>
                </ul>
            </div>
        </div>
        <div class="border-top mt-4 pt-3 text-center text-muted small">
            &copy; 2026 VitaNatural. Todos los derechos reservados.
        </div>
    </div>
</footer>
