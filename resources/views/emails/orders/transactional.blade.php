@php
    $imageCids = [];

    foreach ($embedded_images as $embeddedImage) {
        $imageCids[$embeddedImage['key']] = $message->embedData(
            $embeddedImage['contents'],
            $embeddedImage['filename'],
            $embeddedImage['mime'],
        );
    }
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f3f5f1; color: #1f2923; font-family: Arial, Helvetica, sans-serif; }
        table { border-collapse: collapse; }
        img { border: 0; display: block; }
        .email-shell { width: 100%; background: #f3f5f1; }
        .email-card { width: 100%; max-width: 640px; background: #ffffff; border: 1px solid #dfe5dc; border-radius: 8px; overflow: hidden; }
        .content-padding { padding-left: 36px; padding-right: 36px; }
        .product-image { width: 88px; height: 88px; border-radius: 6px; }
        .summary-label { color: #566259; font-size: 14px; line-height: 20px; }
        .summary-value { color: #1f2923; font-size: 14px; line-height: 20px; text-align: right; }
        @media only screen and (max-width: 520px) {
            .content-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .product-image-cell { width: 72px !important; }
            .product-image { width: 72px !important; height: 72px !important; }
            .product-detail { padding-left: 12px !important; }
            .product-price { width: 112px !important; }
        }
    </style>
</head>
<body>
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
<table class="email-shell" role="presentation" width="100%">
    <tr>
        <td align="center" style="padding: 24px 12px;">
            <table class="email-card" role="presentation" width="640">
                <tr>
                    <td class="content-padding" style="background:#245f38;padding-top:22px;padding-bottom:22px;">
                        <table role="presentation" width="100%">
                            <tr>
                                <td style="color:#ffffff;font-size:20px;font-weight:700;line-height:26px;">{{ $brand }}</td>
                                <td align="right" style="color:#dcebd6;font-size:12px;line-height:18px;">{{ $order_code }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="content-padding" style="padding-top:32px;padding-bottom:12px;">
                        <div style="color:#526158;font-size:14px;line-height:21px;">Hola {{ $recipient_name }},</div>
                        <h1 style="margin:10px 0 8px;color:#173d27;font-size:26px;line-height:33px;letter-spacing:0;">{{ $heading }}</h1>
                        <div style="display:inline-block;padding:5px 9px;border-radius:6px;font-size:12px;font-weight:700;line-height:16px;
                            @if ($status_tone === 'pending') background:#fff2cc;color:#855b00;
                            @elseif ($status_tone === 'cancelled') background:#fde8e7;color:#a12a24;
                            @else background:#fff0d9;color:#975700; @endif">
                            {{ $status_label }}
                        </div>
                        <p style="margin:16px 0 0;color:#344139;font-size:15px;line-height:23px;">{{ $summary }}</p>
                    </td>
                </tr>

                @if ($has_items)
                    <tr>
                        <td class="content-padding" style="padding-top:16px;">
                            <div style="padding-bottom:10px;border-bottom:1px solid #dfe5dc;color:#173d27;font-size:15px;font-weight:700;line-height:22px;">
                                Productos incluidos
                            </div>
                        </td>
                    </tr>
                    @foreach ($items as $item)
                        <tr>
                            <td class="content-padding" style="padding-top:14px;padding-bottom:14px;border-bottom:1px solid #edf0eb;">
                                <table role="presentation" width="100%">
                                    <tr>
                                        <td class="product-image-cell" width="88" valign="middle">
                                            <img
                                                class="product-image"
                                                src="{{ $imageCids[$item['image_key']] }}"
                                                width="88"
                                                height="88"
                                                alt="{{ $item['name'] }}"
                                            >
                                        </td>
                                        <td class="product-detail" valign="middle" style="padding-left:16px;">
                                            <div style="color:#1f2923;font-size:15px;font-weight:700;line-height:21px;">{{ $item['name'] }}</div>
                                            @if ($item['presentation'])
                                                <div style="margin-top:4px;color:#67726b;font-size:13px;line-height:19px;">{{ $item['presentation'] }}</div>
                                            @endif
                                        </td>
                                        <td class="product-price" width="142" valign="middle" align="right" style="padding-left:10px;">
                                            @if ($item['has_multiple_units'])
                                                <div style="color:#5e6962;font-size:13px;line-height:18px;">
                                                    {{ $item['quantity'] }} x {{ $item['unit_price'] }}
                                                </div>
                                                <div style="margin-top:3px;color:#1f2923;font-size:15px;font-weight:700;line-height:20px;">
                                                    {{ $item['line_subtotal'] }}
                                                </div>
                                            @else
                                                <div style="color:#1f2923;font-size:15px;font-weight:700;line-height:20px;">
                                                    {{ $item['unit_price'] }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td class="content-padding" style="padding-top:22px;padding-bottom:8px;">
                        <table role="presentation" width="100%" style="background:#f7f8f5;border:1px solid #e2e7df;border-radius:6px;">
                            <tr>
                                <td colspan="2" style="padding:16px 16px 8px;color:#173d27;font-size:14px;font-weight:700;line-height:20px;">Resumen del pedido</td>
                            </tr>
                            <tr>
                                <td class="summary-label" style="padding:5px 16px;">Subtotal de productos</td>
                                <td class="summary-value" style="padding:5px 16px;">{{ $products_subtotal }}</td>
                            </tr>
                            @if ($has_discount)
                                <tr>
                                    <td class="summary-label" style="padding:5px 16px;">Descuento</td>
                                    <td class="summary-value" style="padding:5px 16px;color:#26733e;">-{{ $discount }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="summary-label" style="padding:5px 16px;">Entrega</td>
                                <td class="summary-value" style="padding:5px 16px;">{{ $shipping }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label" style="padding:5px 16px 11px;">Modalidad</td>
                                <td class="summary-value" style="padding:5px 16px 11px;">{{ $delivery_method }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px 16px;border-top:1px solid #dce2d9;color:#173d27;font-size:16px;font-weight:700;line-height:22px;">Total</td>
                                <td align="right" style="padding:12px 16px 16px;border-top:1px solid #dce2d9;color:#173d27;font-size:18px;font-weight:700;line-height:22px;">{{ $total }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="content-padding" style="padding-top:10px;padding-bottom:8px;">
                        <div style="padding:13px 14px;background:#f1f6ee;border-left:3px solid #4d7c3a;color:#3e4b42;font-size:13px;line-height:20px;">
                            {{ $notice }}
                            @if ($reservation_expiration)
                                <br>Reserva vigente hasta el {{ $reservation_expiration }}.
                            @endif
                        </div>
                    </td>
                </tr>

                @if ($action_url)
                    <tr>
                        <td class="content-padding" align="center" style="padding-top:18px;padding-bottom:28px;">
                            <a href="{{ $action_url }}" style="display:inline-block;padding:12px 22px;background:#ef6c00;border-radius:6px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;line-height:20px;">
                                {{ $action_label }}
                            </a>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td class="content-padding" style="padding-top:18px;padding-bottom:20px;background:#f7f8f5;border-top:1px solid #e2e7df;color:#738078;font-size:11px;line-height:17px;text-align:center;">
                        Este es un correo transaccional sobre tu pedido {{ $order_code }}.<br>
                        &copy; {{ $year }} {{ $brand }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
