@extends('layouts.admin')

@section('title', $order->code.' | Pedidos | VitaNatural Admin')
@section('adminActive', 'orders')

@section('content')
<div class="mb-4">
    <a class="small text-vn-green fw-bold" href="{{ route('admin.orders.index', $backQuery) }}">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a pedidos
    </a>
    <div class="admin-order-detail-heading mt-3">
        <div>
            <p class="text-muted small mb-1">Pedido</p>
            <h1 class="h3 fw-black mb-1">{{ $order->code }}</h1>
            <p class="small text-muted mb-0">Realizado el {{ $detail['created_at'] }}</p>
        </div>
        <x-account.order-status :status="$detail['commercial_status']" />
    </div>
</div>

<div class="admin-order-detail-grid">
    <div class="admin-order-detail-column">
        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-products-title">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h2 class="h5 fw-black mb-0" id="admin-order-products-title">Productos</h2>
                <span class="small text-muted text-end">
                    {{ $detail['product_count'] }} {{ $detail['product_count'] === 1 ? 'producto' : 'productos' }}
                    <span aria-hidden="true">&middot;</span>
                    {{ $detail['total_quantity'] }} {{ $detail['total_quantity'] === 1 ? 'unidad' : 'unidades' }}
                </span>
            </div>

            <div class="admin-order-product-list">
                @foreach($detail['items'] as $item)
                    <article class="admin-order-product-row">
                        <img class="admin-order-product-image" src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                        <div class="admin-order-product-copy">
                            <strong>{{ $item['name'] }}</strong>
                            @if($item['presentation'])
                                <span>{{ $item['presentation'] }}</span>
                            @endif
                            <span>
                                SKU: {{ $item['sku'] }}
                                <span class="mx-1" aria-hidden="true">&middot;</span>
                                {{ $item['tax_label'] }}
                            </span>
                            <span>
                                {{ $item['quantity'] }} x {{ $item['formatted_unit_price'] }}
                            </span>
                            @if($item['has_discount'])
                                <span class="text-success">
                                    Descuento: {{ $item['formatted_discount'] }}
                                </span>
                            @endif
                        </div>
                        <div class="admin-order-product-total">
                            <span>Subtotal</span>
                            <strong>{{ $item['formatted_total'] }}</strong>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-delivery-title">
            <div class="admin-order-section-heading">
                <span class="admin-order-section-icon" aria-hidden="true">
                    <i class="bi {{ $detail['delivery']['icon'] }}"></i>
                </span>
                <div>
                    <h2 class="h5 fw-black mb-0" id="admin-order-delivery-title">{{ $detail['delivery']['method_label'] }}</h2>
                    @if($detail['delivery']['estimate'])
                        <span class="small text-muted">{{ $detail['delivery']['estimate_label'] }} {{ $detail['delivery']['estimate'] }}</span>
                    @endif
                </div>
            </div>

            @if($detail['delivery']['is_pickup'])
                <dl class="admin-order-info-list mt-3 mb-0">
                    <div>
                        <dt>Direccion de recojo</dt>
                        <dd>{{ $detail['delivery']['address'] }}</dd>
                    </div>
                </dl>
            @else
                <dl class="admin-order-info-list mt-3 mb-0">
                    <div>
                        <dt>Destinatario</dt>
                        <dd>{{ $detail['delivery']['recipient_name'] }}</dd>
                    </div>
                    @if($detail['delivery']['phone'])
                        <div>
                            <dt>Telefono</dt>
                            <dd>{{ $detail['delivery']['phone'] }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Direccion</dt>
                        <dd>{{ $detail['delivery']['address'] }}</dd>
                    </div>
                    <div>
                        <dt>Ubicacion</dt>
                        <dd>
                            {{ $detail['delivery']['location'] }}
                            @if($order->delivery_ubigeo)
                                <span class="d-block small text-muted">UBIGEO {{ $order->delivery_ubigeo }}</span>
                            @endif
                        </dd>
                    </div>
                    @if($detail['delivery']['reference'])
                        <div>
                            <dt>Referencia</dt>
                            <dd>{{ $detail['delivery']['reference'] }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @if($detail['delivery']['reservation_expires_at'])
                <div class="admin-order-note mt-3">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    Reserva vigente hasta el {{ $detail['delivery']['reservation_expires_at'] }}.
                </div>
            @endif
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-reservations-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-reservations-title">Reservas de inventario</h2>
            @if($detail['reservation_summary'] === null)
                <p class="small text-muted mb-0">Este pedido no tiene reservas registradas.</p>
            @else
                @php($reservationSummary = $detail['reservation_summary'])
                <div class="admin-order-reservation-summary">
                    <span class="admin-order-reservation-icon" aria-hidden="true">
                        <i class="bi bi-box-seam"></i>
                    </span>
                    <div>
                        <strong>
                            {{ $reservationSummary['product_count'] }}
                            {{ $reservationSummary['product_count'] === 1 ? 'producto' : 'productos' }}
                            <span aria-hidden="true">&middot;</span>
                            {{ $reservationSummary['total_quantity'] }}
                            {{ $reservationSummary['total_quantity'] === 1 ? 'unidad' : 'unidades' }}
                        </strong>
                        <span>{{ $reservationSummary['description'] }}</span>
                    </div>
                    <x-admin.status-badge :status="$reservationSummary['status']" />
                </div>

                @if($reservationSummary['is_mixed'])
                    <div class="admin-order-reservation-breakdown" aria-label="Resumen por estado">
                        @foreach($reservationSummary['breakdown'] as $status)
                            <span>
                                {{ $status['label'] }}
                                <strong>{{ $status['count'] }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif

                <details class="admin-order-reservation-details mt-3">
                    <summary>Ver detalle por producto</summary>
                    <div class="admin-order-record-list mt-3">
                        @foreach($detail['reservations'] as $reservation)
                            <article class="admin-order-record-row">
                                <div>
                                    <strong>{{ $reservation['product'] }}</strong>
                                    @if($reservation['sku'])
                                        <span>SKU {{ $reservation['sku'] }}</span>
                                    @endif
                                    <span>{{ $reservation['quantity'] }} {{ $reservation['quantity'] === 1 ? 'unidad' : 'unidades' }}</span>
                                </div>
                                <div class="admin-order-record-meta">
                                    @if($reservationSummary['is_mixed'])
                                        <x-admin.status-badge :status="$reservation['status']" />
                                    @endif
                                    @if($reservation['closed_at'])
                                        <span>{{ $reservation['closed_at'] }}</span>
                                    @elseif($reservation['expires_at'])
                                        <span>Vence el {{ $reservation['expires_at'] }}</span>
                                    @endif
                                </div>
                                @if($reservation['release_reason'])
                                    <p class="mb-0"><strong>Motivo:</strong> {{ $reservation['release_reason'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-history-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-history-title">Historial tecnico</h2>
            <div class="admin-order-history-legend" aria-label="Flujos del historial">
                @foreach($detail['history_domains'] as $domain)
                    <span class="is-domain-{{ $domain['key'] }}">
                        <i class="bi {{ $domain['icon'] }}" aria-hidden="true"></i>
                        {{ $domain['label'] }}
                    </span>
                @endforeach
            </div>
            @if($detail['history'] === [])
                <p class="small text-muted mb-0">Este pedido no tiene cambios de estado registrados.</p>
            @else
                <ol class="admin-order-history-list mb-0">
                    @foreach($detail['history'] as $history)
                        <li class="is-domain-{{ $history['domain_key'] }}">
                            <span class="admin-order-history-marker" aria-hidden="true">
                                <i class="bi {{ $history['domain_icon'] }}"></i>
                            </span>
                            <div class="admin-order-history-content">
                                <div class="admin-order-history-heading">
                                    <strong>{{ $history['domain'] }}</strong>
                                    <span>{{ $history['occurred_at'] }}</span>
                                </div>
                                @if($history['reservation_summary'])
                                    <p class="admin-order-history-summary mb-1">{{ $history['reservation_summary'] }}</p>
                                @endif
                                <div class="admin-order-history-transition">
                                    {{ $history['from'] }}
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    <strong>{{ $history['to'] }}</strong>
                                </div>
                                <span class="small text-muted">
                                    Por {{ $history['actor'] }}
                                    @if($history['actor_email'])
                                        ({{ $history['actor_email'] }})
                                    @endif
                                </span>
                                @if($history['reason'])
                                    <p class="small mb-0 mt-2"><strong>Motivo:</strong> {{ $history['reason'] }}</p>
                                @endif
                                @if($history['reservation_items'] !== [])
                                    <details class="admin-order-history-reservations mt-2">
                                        <summary>
                                            Ver {{ $history['reservation_count'] }}
                                            {{ $history['reservation_count'] === 1 ? 'producto' : 'productos' }}
                                        </summary>
                                        <ul>
                                            @foreach($history['reservation_items'] as $item)
                                                <li>
                                                    <span>
                                                        <strong>{{ $item['product'] }}</strong>
                                                        @if($item['sku'])
                                                            <small>SKU {{ $item['sku'] }}</small>
                                                        @endif
                                                    </span>
                                                    <strong>
                                                        {{ $item['quantity'] }}
                                                        {{ $item['quantity'] === 1 ? 'unidad' : 'unidades' }}
                                                    </strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if($history['metadata_json'])
                                    <details class="admin-order-history-metadata mt-2">
                                        <summary>Datos tecnicos</summary>
                                        <pre class="mb-0">{{ $history['metadata_json'] }}</pre>
                                    </details>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-communications-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-communications-title">Comunicaciones</h2>
            @if($detail['communications'] === [])
                <p class="small text-muted mb-0">Todavia no se registraron comunicaciones para este pedido.</p>
            @else
                <div class="admin-order-record-list">
                    @foreach($detail['communications'] as $communication)
                        <article class="admin-order-record-row">
                            <div class="admin-order-communication-heading">
                                <div>
                                    <span class="small text-muted">{{ $communication['kind'] }}</span>
                                    <strong class="d-block">{{ $communication['event'] }}</strong>
                                </div>
                                <x-admin.status-badge :status="$communication['status']" />
                            </div>
                            <dl class="admin-order-inline-data mb-0">
                                <div>
                                    <dt>Destinatario</dt>
                                    <dd>{{ $communication['recipient'] }}</dd>
                                </div>
                                <div>
                                    <dt>Intentos</dt>
                                    <dd>{{ $communication['attempts'] }}</dd>
                                </div>
                                <div>
                                    <dt>Fecha</dt>
                                    <dd>{{ $communication['occurred_at'] ?? 'Sin registrar' }}</dd>
                                </div>
                            </dl>
                            @if($communication['actor'])
                                <p class="small mb-0"><strong>Administrador:</strong> {{ $communication['actor'] }}</p>
                            @endif
                            @if($communication['error'])
                                <p class="small text-danger mb-0"><strong>Error:</strong> {{ $communication['error'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="admin-order-detail-column">
        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-amounts-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-amounts-title">Resumen de compra</h2>
            <dl class="admin-order-amount-list mb-0">
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
                <div class="admin-order-grand-total">
                    <dt>Total</dt>
                    <dd>{{ $detail['amounts']['total'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-statuses-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-statuses-title">Estados del pedido</h2>
            <div class="admin-order-technical-statuses">
                @foreach($detail['technical_statuses'] as $status)
                    <div>
                        <span>{{ $status['label'] }}</span>
                        <x-admin.status-badge :status="$status['value']" />
                        @if($status['explanation'])
                            <small class="admin-order-status-explanation">{{ $status['explanation'] }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-contact-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-contact-title">Cliente y contacto</h2>
            <dl class="admin-order-info-list mb-0">
                <div>
                    <dt>Nombre del pedido</dt>
                    <dd>{{ $detail['contact']['name'] }}</dd>
                </div>
                <div>
                    <dt>Correo del pedido</dt>
                    <dd class="text-break">{{ $detail['contact']['email'] }}</dd>
                </div>
                @if($detail['contact']['phone'])
                    <div>
                        <dt>Telefono</dt>
                        <dd>{{ $detail['contact']['phone'] }}</dd>
                    </div>
                @endif
            </dl>

            <div class="admin-order-subsection mt-3 pt-3">
                <h3 class="small fw-black mb-2">Cuenta actual</h3>
                @if($detail['account']['exists'])
                    <p class="small mb-1">{{ $detail['account']['name'] }}</p>
                    <p class="small text-muted text-break mb-2">{{ $detail['account']['email'] }}</p>
                    <x-admin.status-badge :status="$detail['account']['verified'] ? 'Correo verificado' : 'Correo sin verificar'" />
                @else
                    <p class="small text-muted mb-0">La cuenta asociada ya no esta disponible.</p>
                @endif
            </div>
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-fiscal-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-fiscal-title">Solicitud fiscal</h2>
            <dl class="admin-order-info-list mb-0">
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
                @if($detail['fiscal']['identity'])
                    <div>
                        <dt>{{ $detail['fiscal']['identity_type'] }}</dt>
                        <dd>{{ $detail['fiscal']['identity'] }}</dd>
                    </div>
                @endif
                @if($detail['fiscal']['address'])
                    <div>
                        <dt>Direccion fiscal</dt>
                        <dd>{{ $detail['fiscal']['address'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Correo fiscal</dt>
                    <dd class="text-break">{{ $detail['fiscal']['email'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-documents-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-documents-title">Documentos fiscales</h2>
            @if($detail['documents'] === [])
                <p class="small text-muted mb-0">Todavia no se registro un comprobante para este pedido.</p>
            @else
                <div class="admin-order-record-list">
                    @foreach($detail['documents'] as $document)
                        <article class="admin-order-record-row">
                            <div class="admin-order-communication-heading">
                                <div>
                                    <strong>{{ $document['type'] }}</strong>
                                    <span class="d-block small text-muted">{{ $document['reference'] }}</span>
                                </div>
                                <x-admin.status-badge :status="$document['status']" />
                            </div>
                            <dl class="admin-order-inline-data mb-0">
                                <div>
                                    <dt>Emision</dt>
                                    <dd>{{ $document['issued_at'] }}</dd>
                                </div>
                                <div>
                                    <dt>Archivos</dt>
                                    <dd>
                                        PDF {{ $document['has_pdf'] ? 'registrado' : 'ausente' }}
                                        @if($document['has_xml'])
                                            <span aria-hidden="true">&middot;</span> XML registrado
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>Registrado por</dt>
                                    <dd>{{ $document['registrar'] }}</dd>
                                </div>
                            </dl>
                            @if($document['parent_reference'])
                                <p class="small mb-0"><strong>Relacionado con:</strong> {{ $document['parent_reference'] }}</p>
                            @endif
                            @if($document['annulment_reason'])
                                <p class="small text-danger mb-0"><strong>Anulacion:</strong> {{ $document['annulment_reason'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-terms-title">
            <h2 class="h5 fw-black mb-3" id="admin-order-terms-title">Aceptacion legal</h2>
            @if($detail['terms']['version'])
                <dl class="admin-order-info-list mb-0">
                    <div>
                        <dt>Version aceptada</dt>
                        <dd>{{ $detail['terms']['version'] }}</dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd>{{ $detail['terms']['accepted_at'] ?? 'Sin registrar' }}</dd>
                    </div>
                    @if($detail['terms']['fingerprint'])
                        <div>
                            <dt>Huella del contenido</dt>
                            <dd class="font-monospace small text-break">{{ $detail['terms']['fingerprint'] }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <p class="small text-muted mb-0">Este pedido no conserva una aceptacion legal asociada.</p>
            @endif
        </section>
    </div>
</div>
@endsection
