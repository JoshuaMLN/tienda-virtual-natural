@extends('layouts.account')

@section('title', $order->code.' | Mis pedidos | VitaNatural')
@section('accountActive', 'orders')

@section('accountContent')
<div class="mb-4">
    <a class="account-back-link" href="{{ route('account.orders') }}">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a mis pedidos
    </a>
    <div class="customer-order-detail-heading mt-3">
        <div>
            <p class="text-muted small mb-1">Pedido</p>
            <h1 class="section-title mb-1">{{ $order->code }}</h1>
            <p class="customer-order-created-at mb-0">Realizado el {{ $detail['created_at'] }}</p>
        </div>
        <div class="customer-order-detail-heading-actions">
            <x-account.order-status :status="$commercialStatus" />
            @if($capabilities->canCancel)
                <button
                    class="btn btn-sm btn-outline-danger"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#cancelOrderModal-{{ $order->getKey() }}"
                >
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Cancelar pedido
                </button>
            @endif
        </div>
    </div>
</div>

@if($detail['delivery']['primary_notice'])
    @php($primaryNotice = $detail['delivery']['primary_notice'])
    <section class="customer-order-fulfillment-notice is-{{ $primaryNotice['tone'] }} mb-4" role="status" aria-labelledby="order-fulfillment-notice-title">
        <span class="customer-order-fulfillment-notice-icon" aria-hidden="true">
            <i class="bi {{ $primaryNotice['icon'] }}"></i>
        </span>
        <div class="customer-order-fulfillment-notice-copy">
            <h2 class="h6 fw-black mb-1" id="order-fulfillment-notice-title">{{ $primaryNotice['title'] }}</h2>
            <p class="mb-0">{{ $primaryNotice['message'] }}</p>
        </div>
    </section>
@endif

@if($detail['cancellation'])
    <section class="customer-order-cancellation-notice mb-4" aria-labelledby="order-cancellation-title">
        <span class="customer-order-cancellation-icon" aria-hidden="true">
            <i class="bi bi-x-lg"></i>
        </span>
        <div>
            <h2 class="h6 fw-black mb-1" id="order-cancellation-title">{{ $detail['cancellation']['title'] }}</h2>
            <p class="mb-1">
                <span class="fw-bold">Motivo:</span>
                {{ $detail['cancellation']['reason'] }}
            </p>
            @if($detail['cancellation']['refund_message'])
                <p class="customer-order-cancellation-refund mb-1">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                    {{ $detail['cancellation']['refund_message'] }}
                </p>
            @endif
            <time
                class="customer-order-cancellation-time"
                datetime="{{ $detail['cancellation']['occurred_at']->toAtomString() }}"
            >
                Cancelado el {{ $detail['cancellation']['formatted_date'] }}
            </time>
        </div>
    </section>
@endif

@if($capabilities->shouldContactSupport)
    <div class="customer-order-support-notice mb-4" role="note">
        <span class="customer-order-support-icon" aria-hidden="true"><i class="bi bi-headset"></i></span>
        <div class="customer-order-support-copy">
            <strong>Necesitas cambiar o cancelar este pedido?</strong>
            <span>El pago ya fue confirmado. Nuestro equipo revisara tu solicitud antes de modificar el pedido.</span>
        </div>
        <div class="customer-order-support-actions">
            @if($support['whatsapp_url'])
                <a class="btn btn-sm btn-green" href="{{ $support['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    WhatsApp {{ $support['whatsapp_display'] }}
                </a>
            @endif
            <a class="btn btn-sm btn-vn-outline" href="mailto:{{ $support['email'] }}">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                {{ $support['email'] }}
            </a>
        </div>
    </div>
@endif

<div class="customer-order-detail-grid">
    <div class="customer-order-detail-column customer-order-detail-column-primary">
    <section class="account-card customer-order-products p-3 p-lg-4" aria-labelledby="order-products-title">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="h5 fw-black mb-0" id="order-products-title">Productos</h2>
            <span class="small text-muted text-end">
                {{ $detail['product_count'] }} {{ $detail['product_count'] === 1 ? 'producto' : 'productos' }}
                <span aria-hidden="true">&middot;</span>
                {{ $detail['total_quantity'] }} {{ $detail['total_quantity'] === 1 ? 'unidad' : 'unidades' }}
            </span>
        </div>

        <div class="checkout-product-list">
            @foreach($detail['items'] as $item)
                <article class="checkout-product-row customer-order-product-row">
                    @if($item['product_url'])
                        <a class="checkout-product-image" href="{{ $item['product_url'] }}" aria-label="Ver {{ $item['name'] }}">
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                        </a>
                    @else
                        <span class="checkout-product-image">
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                        </span>
                    @endif

                    <div class="checkout-product-content">
                        @if($item['product_url'])
                            <a class="fw-black customer-order-product-name" href="{{ $item['product_url'] }}">{{ $item['name'] }}</a>
                        @else
                            <strong class="customer-order-product-name">{{ $item['name'] }}</strong>
                        @endif
                        @if($item['presentation'])
                            <p class="small text-muted mb-1">{{ $item['presentation'] }}</p>
                        @endif
                        <div class="small text-muted">
                            Cantidad: {{ $item['quantity'] }}
                            <span class="mx-1" aria-hidden="true">&middot;</span>
                            Precio unitario: {{ $item['formatted_unit_price'] }}
                            <span class="mx-1" aria-hidden="true">&middot;</span>
                            {{ $item['tax_label'] }}
                        </div>
                        <div class="customer-order-product-sku">SKU: {{ $item['sku'] }}</div>
                        @if($item['has_discount'])
                            <div class="small text-success mt-1">
                                Antes {{ $item['formatted_gross_total'] }}
                                <span class="mx-1" aria-hidden="true">&middot;</span>
                                Descuento {{ $item['formatted_discount'] }}
                            </div>
                        @endif
                    </div>

                    <div class="customer-order-product-total">
                        <span>Subtotal</span>
                        <strong>{{ $item['formatted_total'] }}</strong>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="account-card customer-order-delivery p-3 p-lg-4" aria-labelledby="order-delivery-title">
        <div class="customer-order-card-title">
            <i class="bi {{ $detail['delivery']['icon'] }}" aria-hidden="true"></i>
            <div>
                <h2 class="h5 fw-black mb-0" id="order-delivery-title">{{ $detail['delivery']['method_label'] }}</h2>
            </div>
        </div>

        @if($detail['delivery']['is_pickup'])
            <p class="mb-0 mt-3">{{ $detail['delivery']['address'] }}</p>
        @else
            <div class="mt-3">
                <div class="fw-bold">{{ $detail['delivery']['recipient_name'] }}</div>
                @if($detail['delivery']['phone'])
                    <div class="small text-muted"><i class="bi bi-phone me-1" aria-hidden="true"></i>{{ $detail['delivery']['phone'] }}</div>
                @endif
                <p class="mb-1 mt-3">{{ $detail['delivery']['address'] }}</p>
                <p class="small text-muted mb-0">{{ $detail['delivery']['location'] }}</p>
                @if($detail['delivery']['reference'])
                    <p class="small mb-0 mt-2"><span class="fw-bold">Referencia:</span> {{ $detail['delivery']['reference'] }}</p>
                @endif
            </div>
        @endif

        @if($detail['delivery']['reservation_expires_at'])
            <div class="customer-order-reservation-note mt-3">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <span>Reserva vigente hasta el {{ $detail['delivery']['reservation_expires_at'] }}</span>
            </div>
        @endif

    </section>

    <section class="account-card customer-order-timeline-card p-3 p-lg-4" aria-labelledby="order-timeline-title">
        <h2 class="h5 fw-black mb-4" id="order-timeline-title">Seguimiento del pedido</h2>
        <ol class="customer-order-timeline mb-0">
            @foreach($detail['timeline'] as $timelineItem)
                @php($event = $timelineItem['event'])
                <li class="customer-order-timeline-event is-{{ $event->tone }}">
                    <span class="customer-order-timeline-icon" aria-hidden="true">
                        <i class="bi {{ $event->icon }}"></i>
                    </span>
                    <div>
                        <h3 class="h6 fw-black mb-1">{{ $event->title }}</h3>
                        <p class="small text-muted mb-1">{{ $event->description }}</p>
                        <time class="customer-order-timeline-time" datetime="{{ $event->occurredAt->toAtomString() }}">
                            {{ $timelineItem['formatted_date'] }}
                        </time>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
    </div>

    <div class="customer-order-detail-column customer-order-detail-column-secondary">
        <section class="account-card customer-order-amounts p-3 p-lg-4" aria-labelledby="order-amounts-title">
            <h2 class="h5 fw-black mb-3" id="order-amounts-title">Resumen de compra</h2>
            <dl class="customer-order-amount-list mb-0">
                <div>
                    <dt>Productos</dt>
                    <dd>{{ $detail['amounts']['products_subtotal'] }}</dd>
                </div>
                @if($detail['amounts']['has_discount'])
                    <div class="text-success">
                        <dt>Descuento</dt>
                        <dd>-{{ $detail['amounts']['discount'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Envio</dt>
                    <dd>{{ $detail['amounts']['shipping'] }}</dd>
                </div>

                <div class="customer-order-amount-divider" aria-hidden="true"></div>

                @if($detail['amounts']['has_taxable_value'])
                    <div>
                        <dt>Valor gravado</dt>
                        <dd>{{ $detail['amounts']['taxable_value'] }}</dd>
                    </div>
                @endif
                @if($detail['amounts']['has_exempt_value'])
                    <div>
                        <dt>Valor exonerado</dt>
                        <dd>{{ $detail['amounts']['exempt_value'] }}</dd>
                    </div>
                @endif
                @if($detail['amounts']['has_unaffected_value'])
                    <div>
                        <dt>Valor inafecto</dt>
                        <dd>{{ $detail['amounts']['unaffected_value'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt>IGV incluido</dt>
                    <dd>{{ $detail['amounts']['tax'] }}</dd>
                </div>

                <div class="customer-order-amount-divider" aria-hidden="true"></div>

                <div class="customer-order-grand-total">
                    <dt>Total</dt>
                    <dd>{{ $detail['amounts']['total'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="account-card customer-order-contact p-3 p-lg-4" aria-labelledby="order-contact-title">
            <h2 class="h5 fw-black mb-3" id="order-contact-title">Contacto</h2>
            <dl class="customer-order-info-list mb-0">
                <div>
                    <dt>Cliente</dt>
                    <dd>{{ $detail['contact']['name'] }}</dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd class="text-break">{{ $detail['contact']['email'] }}</dd>
                </div>
                @if($detail['contact']['phone'])
                    <div>
                        <dt>Telefono</dt>
                        <dd>{{ $detail['contact']['phone'] }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="account-card customer-order-fiscal p-3 p-lg-4" aria-labelledby="order-fiscal-title">
            <h2 class="h5 fw-black mb-3" id="order-fiscal-title">Comprobante solicitado</h2>
            <dl class="customer-order-info-list mb-0">
                <div>
                    <dt>Tipo</dt>
                    <dd>{{ $detail['fiscal']['type'] }}</dd>
                </div>
                @if($detail['fiscal']['holder'])
                    <div>
                        <dt>{{ $detail['fiscal']['type'] === 'Factura' ? 'Razon social' : 'Titular' }}</dt>
                        <dd>{{ $detail['fiscal']['holder'] }}</dd>
                    </div>
                @endif
                @if($detail['fiscal']['masked_identity'])
                    <div>
                        <dt>{{ $detail['fiscal']['identity_type'] }}</dt>
                        <dd>{{ $detail['fiscal']['masked_identity'] }}</dd>
                    </div>
                @endif
                @if($detail['fiscal']['address'])
                    <div>
                        <dt>Direccion fiscal</dt>
                        <dd>{{ $detail['fiscal']['address'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Correo</dt>
                    <dd class="text-break">{{ $detail['fiscal']['email'] }}</dd>
                </div>
            </dl>
        </section>

        @if($detail['fiscal_documents']['visible'])
            <section class="account-card customer-order-documents p-3 p-lg-4" aria-labelledby="order-documents-title">
                <h2 class="h5 fw-black mb-3" id="order-documents-title">Documentos fiscales</h2>

                @if($detail['fiscal_documents']['pending_issue'])
                    <div class="customer-order-document-pending" role="status">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <div>
                            <strong>Comprobante pendiente de emision</strong>
                            <p class="mb-0">Lo encontraras aqui cuando la tienda registre el documento emitido.</p>
                        </div>
                    </div>
                @else
                    <div class="customer-order-document-list">
                        @foreach($detail['fiscal_documents']['items'] as $document)
                            <article class="customer-order-document-row{{ $document['is_annulled'] ? ' is-annulled' : '' }}">
                                <span class="customer-order-document-icon" aria-hidden="true">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </span>
                                <div class="customer-order-document-copy">
                                    <div class="customer-order-document-heading">
                                        <strong>{{ $document['type'] }}</strong>
                                        <span class="customer-order-document-status{{ $document['is_annulled'] ? ' is-annulled' : '' }}">
                                            {{ $document['status'] }}
                                        </span>
                                    </div>
                                    <span class="customer-order-document-reference">{{ $document['reference'] }}</span>
                                    <span class="customer-order-document-date">Emitido el {{ $document['issued_at'] }}</span>
                                </div>
                                <a
                                    class="btn btn-sm btn-vn-outline customer-order-document-download"
                                    href="{{ $document['download_url'] }}"
                                    aria-label="Descargar {{ $document['type'] }} {{ $document['reference'] }} en PDF"
                                >
                                    <i class="bi bi-download" aria-hidden="true"></i>
                                    Descargar PDF
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</div>

@if($capabilities->canCancel)
    <x-account.order-cancel-modal :order="$order" />
@endif
@endsection
