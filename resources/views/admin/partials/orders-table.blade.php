@php
    $orders = [
        ['id' => 'VN-2024-000123', 'client' => 'Maria Fernanda R.', 'date' => '16/05/2024 10:23', 'total' => 'S/ 176.60', 'payment' => 'Pagado', 'shipping' => 'Pendiente'],
        ['id' => 'VN-2024-000122', 'client' => 'Diego Salazar', 'date' => '16/05/2024 09:45', 'total' => 'S/ 79.90', 'payment' => 'Pagado', 'shipping' => 'Enviado'],
        ['id' => 'VN-2024-000121', 'client' => 'Carla Mendoza', 'date' => '16/05/2024 08:31', 'total' => 'S/ 74.80', 'payment' => 'Pendiente', 'shipping' => 'Pendiente'],
        ['id' => 'VN-2024-000120', 'client' => 'Luis Garcia', 'date' => '15/05/2024 17:22', 'total' => 'S/ 62.90', 'payment' => 'Pagado', 'shipping' => 'Entregado'],
    ];
@endphp

<div class="table-responsive">
    <table class="table mb-0">
        <thead>
            <tr><th>Pedido</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pago</th><th>Envio</th><th></th></tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
                <tr>
                    <td><strong>{{ $order['id'] }}</strong></td>
                    <td>{{ $order['client'] }}</td>
                    <td>{{ $order['date'] }}</td>
                    <td><strong>{{ $order['total'] }}</strong></td>
                    <td><x-admin.status-badge :status="$order['payment']" /></td>
                    <td><x-admin.status-badge :status="$order['shipping']" /></td>
                    <td><a class="btn btn-sm btn-light" href="{{ route('admin.orders.show') }}" aria-label="Ver pedido"><i class="bi bi-eye"></i></a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
