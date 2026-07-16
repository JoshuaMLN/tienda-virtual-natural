<div class="trust-strip p-3 p-lg-4">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="trust-item">
                <i class="bi bi-truck"></i>
                @if($storeSettings->freeShippingEnabled())
                    <div><strong>Envio gratis</strong><br><span class="small text-muted">Desde S/ {{ number_format((float) $storeSettings->freeShippingThreshold(), 2) }} en Lima y Callao</span></div>
                @else
                    <div><strong>Entrega local</strong><br><span class="small text-muted">Lima Metropolitana y Callao</span></div>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <div class="trust-item">
                <i class="bi bi-shield-check"></i>
                <div><strong>Compra 100% segura</strong><br><span class="small text-muted">Protegemos tus datos</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="trust-item">
                <i class="bi bi-whatsapp"></i>
                <div><strong>Soporte por WhatsApp</strong><br><span class="small text-muted">{{ $storeSettings->whatsappDisplay() }}</span></div>
            </div>
        </div>
    </div>
</div>
