{{ $brand }}
{{ $heading }} - {{ $status_label }}

Hola {{ $recipient_name }},

{{ $summary }}

@if ($has_items)
PRODUCTOS INCLUIDOS
@foreach ($items as $item)
@if ($item['has_multiple_units'])
- {{ $item['name'] }}: {{ $item['quantity'] }} x {{ $item['unit_price'] }} = {{ $item['line_subtotal'] }}
@else
- {{ $item['name'] }}: {{ $item['unit_price'] }}
@endif
@if ($item['presentation'])
  {{ $item['presentation'] }}
@endif
@endforeach

@endif
RESUMEN DEL PEDIDO
Subtotal de productos: {{ $products_subtotal }}
@if ($has_discount)
Descuento: -{{ $discount }}
@endif
Entrega: {{ $shipping }}
Modalidad: {{ $delivery_method }}
Total: {{ $total }}

{{ $notice }}
@if ($reservation_expiration)
Reserva vigente hasta el {{ $reservation_expiration }}.
@endif

@if ($action_url)
{{ $action_label }}:
{{ $action_url }}

@endif
Este es un correo transaccional sobre tu pedido {{ $order_code }}.
Copyright {{ $year }} {{ $brand }}.
