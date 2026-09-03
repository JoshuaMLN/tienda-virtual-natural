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

@if($detail['actions'] !== [] || $detail['delivery_tracking']['can_record_attempt'])
    <section class="admin-card admin-order-operation p-3 p-lg-4 mb-4" aria-labelledby="admin-order-operation-title">
        <div class="admin-order-operation-copy">
            <span class="admin-order-section-icon" aria-hidden="true">
                <i class="bi bi-signpost-split"></i>
            </span>
            <div>
                <h2 class="h5 fw-black mb-1" id="admin-order-operation-title">Operacion del pedido</h2>
                <p class="small text-muted mb-0">
                    Acciones disponibles para {{ strtolower($order->delivery_method->label()) }} segun el estado actual.
                </p>
            </div>
        </div>
        <div class="admin-order-operation-actions">
            @if($detail['delivery_tracking']['can_record_attempt'])
                <button
                    class="btn btn-vn-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#admin-delivery-attempt-modal"
                >
                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    Registrar resultado de entrega
                </button>
            @endif
            @foreach($detail['actions'] as $action)
                <button
                    class="btn {{ $action['destructive'] ? 'btn-outline-danger' : 'btn-vn-primary' }}"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#admin-order-action-{{ $action['value'] }}"
                >
                    <i class="bi {{ $action['icon'] }}" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </button>
            @endforeach
        </div>
    </section>
@endif

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

            @if($detail['delivery_tracking']['pickup_ready_at'])
                <div class="admin-order-fulfillment-summary mt-3">
                    <div>
                        <span>Disponible desde</span>
                        <strong>{{ $detail['delivery_tracking']['pickup_ready_at'] }}</strong>
                    </div>
                    <div>
                        <span>Fecha limite de recojo</span>
                        <strong>{{ $detail['delivery_tracking']['pickup_deadline_at'] }}</strong>
                    </div>
                </div>

                @if($detail['delivery_tracking']['is_pickup_overdue'])
                    <div class="alert alert-warning d-flex gap-2 mt-3 mb-0" role="status">
                        <i class="bi bi-exclamation-triangle flex-shrink-0" aria-hidden="true"></i>
                        <span>El plazo de recojo vencio. El pedido requiere coordinacion manual y no sera cancelado ni descartado automaticamente.</span>
                    </div>
                @endif
            @endif
        </section>

        @if(!$detail['delivery']['is_pickup'] && ($detail['delivery_attempts'] !== [] || $detail['delivery_tracking']['can_record_attempt'] || $detail['delivery_tracking']['status_value'] !== 'active'))
            <section class="admin-card p-3 p-lg-4" aria-labelledby="admin-order-delivery-attempts-title">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 fw-black mb-1" id="admin-order-delivery-attempts-title">Intentos de entrega</h2>
                        <p class="small text-muted mb-0">Solo las incidencias atribuibles al cliente consumen un intento.</p>
                    </div>
                    <x-admin.status-badge :status="$detail['delivery_tracking']['status']" />
                </div>

                <div class="admin-order-fulfillment-summary mb-3">
                    <div>
                        <span>Ciclo actual</span>
                        <strong>{{ $detail['delivery_tracking']['cycle'] }} de {{ $detail['delivery_tracking']['max_cycles'] }}</strong>
                    </div>
                    <div>
                        <span>Intentos consumidos</span>
                        <strong>{{ $detail['delivery_tracking']['counted_attempts'] }} de {{ $detail['delivery_tracking']['attempts_per_cycle'] }}</strong>
                    </div>
                </div>

                @if($detail['delivery_tracking']['reshipment_payment_due_at'])
                    <div class="alert alert-warning d-flex gap-2 mb-3" role="status">
                        <i class="bi bi-credit-card flex-shrink-0" aria-hidden="true"></i>
                        <span>
                            No se admiten nuevas visitas hasta confirmar otro pago de envio.
                            Plazo: {{ $detail['delivery_tracking']['reshipment_payment_due_at'] }}.
                        </span>
                    </div>
                @endif

                @if($detail['delivery_attempts'] === [])
                    <p class="small text-muted mb-0">Todavia no se registraron resultados de entrega.</p>
                @else
                    <div class="admin-order-delivery-attempt-list">
                        @foreach($detail['delivery_attempts'] as $attempt)
                            <article class="admin-order-delivery-attempt is-{{ $attempt['result_value'] }}">
                                <span class="admin-order-delivery-attempt-icon" aria-hidden="true">
                                    <i class="bi {{ $attempt['result_value'] === 'delivered' ? 'bi-check-lg' : 'bi-exclamation-lg' }}"></i>
                                </span>
                                <div>
                                    <div class="admin-order-delivery-attempt-heading">
                                        <strong>Ciclo {{ $attempt['cycle'] }}, visita {{ $attempt['attempt_number'] }}</strong>
                                        <span>{{ $attempt['occurred_at'] }}</span>
                                    </div>
                                    <p class="mb-1">
                                        <strong>{{ $attempt['result'] }}</strong>
                                        @if($attempt['attribution'])
                                            <span aria-hidden="true">&middot;</span>
                                            {{ $attempt['attribution'] }}
                                        @endif
                                    </p>
                                    <p class="small text-muted mb-1">
                                        Responsable: {{ $attempt['responsible_name'] }}
                                        @if($attempt['consumes_attempt'])
                                            <span aria-hidden="true">&middot;</span>
                                            Intento {{ $attempt['counted_attempt_number'] }} consumido
                                        @else
                                            <span aria-hidden="true">&middot;</span>
                                            No consume intento
                                        @endif
                                    </p>
                                    @if($attempt['reason'])
                                        <p class="small mb-1"><strong>Motivo:</strong> {{ $attempt['reason'] }}</p>
                                    @endif
                                    <p class="small text-muted mb-0">
                                        Registrado por {{ $attempt['recorded_by'] }}
                                        @if($attempt['recorded_by_email'])
                                            ({{ $attempt['recorded_by_email'] }})
                                        @endif
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

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
                                    @if($reservation['restocked_at'])
                                        <span>Repuesto el {{ $reservation['restocked_at'] }}</span>
                                    @elseif($reservation['closed_at'])
                                        <span>{{ $reservation['closed_at'] }}</span>
                                    @elseif($reservation['expires_at'])
                                        <span>Vence el {{ $reservation['expires_at'] }}</span>
                                    @endif
                                </div>
                                @if($reservation['release_reason'])
                                    <p class="mb-0"><strong>Motivo:</strong> {{ $reservation['release_reason'] }}</p>
                                @endif
                                @if($reservation['restock_reason'])
                                    <p class="mb-0"><strong>Motivo de reposicion:</strong> {{ $reservation['restock_reason'] }}</p>
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
                            @if($communication['note'])
                                <p class="small text-muted mb-0"><strong>Detalle:</strong> {{ $communication['note'] }}</p>
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

@if($detail['delivery_tracking']['can_record_attempt'])
    <div
        class="modal fade admin-order-action-modal"
        id="admin-delivery-attempt-modal"
        tabindex="-1"
        aria-labelledby="admin-delivery-attempt-modal-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.orders.delivery-attempts.store', ['order' => $order->code]) }}" method="POST" data-admin-order-action-form data-delivery-attempt-form>
                    @csrf
                    <input type="hidden" name="operation_token" value="{{ old('operation_token', $detail['delivery_tracking']['operation_token']) }}">
                    @foreach($backQuery as $key => $value)
                        @if($value !== null && $value !== '')
                            <input type="hidden" name="return[{{ $key }}]" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5 fw-black" id="admin-delivery-attempt-modal-title">Registrar resultado de entrega</h2>
                            <p class="small text-muted mb-0">Ciclo {{ $detail['delivery_tracking']['cycle'] }} de {{ $detail['delivery_tracking']['max_cycles'] }}</p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-grid gap-3">
                        <div>
                            <label class="form-label fw-bold" for="delivery-attempt-result">Resultado <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('result') is-invalid @enderror" id="delivery-attempt-result" name="result" required data-delivery-attempt-result>
                                <option value="">Selecciona un resultado</option>
                                @foreach($detail['delivery_tracking']['result_options'] as $result)
                                    <option value="{{ $result->value }}" @selected(old('result') === $result->value)>{{ $result->label() }}</option>
                                @endforeach
                            </select>
                            @error('result')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div data-delivery-attempt-incident-field hidden>
                            <label class="form-label fw-bold" for="delivery-attempt-attribution">Atribucion <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('attribution') is-invalid @enderror" id="delivery-attempt-attribution" name="attribution" data-delivery-attempt-attribution>
                                <option value="">Selecciona una atribucion</option>
                                @foreach($detail['delivery_tracking']['attribution_options'] as $attribution)
                                    <option value="{{ $attribution->value }}" @selected(old('attribution') === $attribution->value)>{{ $attribution->label() }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Solo una incidencia atribuible al cliente consume un intento.</div>
                            @error('attribution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="form-label fw-bold" for="delivery-attempt-responsible">Responsable o transportista <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('responsible_name') is-invalid @enderror" id="delivery-attempt-responsible" name="responsible_name" type="text" maxlength="120" value="{{ old('responsible_name') }}" required>
                            @error('responsible_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="form-label fw-bold" for="delivery-attempt-occurred-at">Fecha y hora <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('occurred_at') is-invalid @enderror" id="delivery-attempt-occurred-at" name="occurred_at" type="datetime-local" step="1" min="{{ $detail['delivery_tracking']['min_occurred_at'] }}" value="{{ old('occurred_at', $detail['delivery_tracking']['default_occurred_at']) }}" required>
                            @error('occurred_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div data-delivery-attempt-incident-field hidden>
                            <label class="form-label fw-bold" for="delivery-attempt-reason">Motivo de la incidencia <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea class="form-control @error('attempt_reason') is-invalid @enderror" id="delivery-attempt-reason" name="attempt_reason" rows="3" minlength="5" maxlength="500" data-delivery-attempt-reason>{{ old('attempt_reason') }}</textarea>
                            @error('attempt_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-vn-primary" type="submit">
                            <span data-submit-label><i class="bi bi-save" aria-hidden="true"></i> Registrar resultado</span>
                            <span class="d-none" data-submit-loading><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Procesando</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@foreach($detail['actions'] as $action)
    <div
        class="modal fade admin-order-action-modal"
        id="admin-order-action-{{ $action['value'] }}"
        tabindex="-1"
        aria-labelledby="admin-order-action-title-{{ $action['value'] }}"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form
                    action="{{ route($action['route_name'], ['order' => $order->code]) }}"
                    method="POST"
                    data-admin-order-action-form
                >
                    @csrf
                    @method('PATCH')
                    @foreach($backQuery as $key => $value)
                        @if($value !== null && $value !== '')
                            <input type="hidden" name="return[{{ $key }}]" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="modal-header">
                        <h2 class="modal-title fs-5 fw-black" id="admin-order-action-title-{{ $action['value'] }}">
                            {{ $action['title'] }}
                        </h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ $action['message'] }}</p>

                        @if($action['paid_cancellation'])
                            <div class="alert alert-warning d-flex gap-2" role="alert">
                                <i class="bi bi-exclamation-triangle flex-shrink-0" aria-hidden="true"></i>
                                <span>
                                    El stock sera repuesto y el pago quedara como <strong>Reembolso pendiente</strong>.
                                    Esto no significa que el dinero ya fue devuelto.
                                </span>
                            </div>
                        @endif

                        @if($action['requires_reason'])
                            <div>
                                <label class="form-label fw-bold" for="admin-order-cancel-reason">
                                    Motivo visible para el cliente <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    class="form-control @error('reason') is-invalid @enderror"
                                    id="admin-order-cancel-reason"
                                    name="reason"
                                    rows="3"
                                    minlength="5"
                                    maxlength="255"
                                    required
                                >{{ old('reason') }}</textarea>
                                <div class="form-text">
                                    Se mostrara en el detalle del pedido y en el correo de cancelacion. No incluyas notas internas.
                                </div>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Volver</button>
                        <button
                            class="btn {{ $action['destructive'] ? 'btn-danger' : 'btn-vn-primary' }}"
                            type="submit"
                        >
                            <span data-submit-label>
                                <i class="bi {{ $action['icon'] }}" aria-hidden="true"></i>
                                {{ $action['label'] }}
                            </span>
                            <span class="d-none" data-submit-loading>
                                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                Procesando
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<script>
    window.addEventListener('load', () => {
        const deliveryAttemptForm = document.querySelector('[data-delivery-attempt-form]');

        if (deliveryAttemptForm) {
            const result = deliveryAttemptForm.querySelector('[data-delivery-attempt-result]');
            const attribution = deliveryAttemptForm.querySelector('[data-delivery-attempt-attribution]');
            const reason = deliveryAttemptForm.querySelector('[data-delivery-attempt-reason]');
            const incidentFields = deliveryAttemptForm.querySelectorAll('[data-delivery-attempt-incident-field]');
            const syncIncidentFields = () => {
                const isIncident = result?.value === 'incident';

                incidentFields.forEach((field) => {
                    field.hidden = !isIncident;
                });

                if (attribution) attribution.required = isIncident;
                if (reason) reason.required = isIncident;
            };

            result?.addEventListener('change', syncIncidentFields);
            syncIncidentFields();
        }

        document.querySelectorAll('[data-admin-order-action-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');

                if (!button) {
                    return;
                }

                button.disabled = true;
                button.querySelector('[data-submit-label]')?.classList.add('d-none');
                button.querySelector('[data-submit-loading]')?.classList.remove('d-none');
            });
        });

        @if($errors->has('reason'))
            const cancelModal = document.getElementById('admin-order-action-cancel');

            if (cancelModal) {
                bootstrap.Modal.getOrCreateInstance(cancelModal).show();
            }
        @endif

        @if($errors->hasAny(['operation_token', 'result', 'attribution', 'responsible_name', 'occurred_at', 'attempt_reason']))
            const deliveryAttemptModal = document.getElementById('admin-delivery-attempt-modal');

            if (deliveryAttemptModal) {
                bootstrap.Modal.getOrCreateInstance(deliveryAttemptModal).show();
            }
        @endif
    });
</script>
@endsection
