<?php

namespace App\Support\Legal;

use App\Enums\LegalDocumentType;
use Illuminate\Support\Str;

class LegalDocumentTemplate
{
    /**
     * @param  array<string, int|string>  $snapshot
     * @return array{title: string, body: string}
     */
    public function render(LegalDocumentType $type, array $snapshot): array
    {
        return match ($type) {
            LegalDocumentType::Terms => [
                'title' => 'Terminos y condiciones',
                'body' => $this->terms($snapshot),
            ],
            LegalDocumentType::Privacy => [
                'title' => 'Politica de privacidad',
                'body' => $this->privacy($snapshot),
            ],
        };
    }

    /** @param array<string, int|string> $snapshot */
    private function terms(array $snapshot): string
    {
        $tradeName = $this->text($snapshot, 'trade_name', 'Tienda demostrativa');
        $provider = $this->text($snapshot, 'provider_name', 'Proveedor pendiente de definir');
        $taxId = $this->text($snapshot, 'tax_id', 'RUC pendiente de definir');
        $address = $this->text($snapshot, 'fiscal_address', 'Domicilio pendiente de definir');
        $email = $this->text($snapshot, 'contact_email', 'Correo pendiente de definir');
        $whatsapp = $this->text($snapshot, 'contact_whatsapp', 'WhatsApp pendiente de definir');
        $hours = $this->text($snapshot, 'business_hours_weekdays', 'Horario pendiente de definir');
        $complaints = $this->text($snapshot, 'complaints_book_url', 'Enlace pendiente de definir');
        $incidentHours = (int) ($snapshot['incident_report_hours'] ?? 48);
        $refundDays = (int) ($snapshot['refund_processing_business_days'] ?? 5);
        $attempts = (int) ($snapshot['delivery_attempts_per_cycle'] ?? 3);
        $cycles = (int) ($snapshot['delivery_max_automatic_cycles'] ?? 2);
        $reshipmentDays = (int) ($snapshot['reshipment_payment_days'] ?? 7);
        $pickupDays = (int) ($snapshot['pickup_hold_days'] ?? 14);

        return <<<MARKDOWN
## 1. Identificacion y alcance

Estos terminos regulan el uso de la tienda **{$tradeName}** y las compras realizadas a traves de ella. El proveedor responsable es **{$provider}**, identificado con **{$taxId}** y domicilio en **{$address}**.

Cuando el sitio se identifique como demostrativo, sus productos, precios, pedidos y pagos son datos de prueba y no representan una oferta comercial real.

## 2. Cuenta del cliente

Para finalizar una compra se requiere una cuenta con correo verificado. El cliente es responsable de mantener sus datos actualizados, proteger sus credenciales y comunicar cualquier uso no autorizado.

## 3. Productos, precios y disponibilidad

Las caracteristicas, presentaciones, advertencias y condiciones de conservacion se informan en cada producto. Los precios se expresan en soles e incluyen los impuestos aplicables. La disponibilidad se vuelve a validar antes de crear el pedido.

Agregar un producto al carrito no reserva stock ni fija definitivamente su precio. Si existe un cambio antes de confirmar, el sistema lo mostrara para que el cliente pueda revisarlo.

## 4. Pedido y pago

El pedido se crea cuando el cliente confirma el resumen vigente. Su aceptacion comercial y la preparacion dependen de la confirmacion del pago. Los pagos con tarjeta se procesan por el proveedor de pago habilitado; la tienda no almacena los datos completos de la tarjeta.

## 5. Entrega y recojo

La cobertura, tarifa y plazo estimado se muestran antes de confirmar. Cada tarifa cubre hasta **{$attempts} intentos** atribuibles al cliente. Los errores de la tienda o del transportista no consumen intentos ni generan un cobro adicional.

Tras agotar un ciclo, el cliente dispone de **{$reshipmentDays} dias calendario** para pagar un nuevo envio o coordinar recojo cuando este disponible. Se gestionan hasta **{$cycles} ciclos automaticos**; despues, el caso pasa a atencion manual.

Los pedidos listos para recojo se conservan inicialmente durante **{$pickupDays} dias calendario** desde la notificacion. Al vencer el plazo se contactara al cliente antes de adoptar una solucion; el pedido no se descarta ni cancela silenciosamente.

## 6. Cancelaciones y reembolsos

El cliente puede solicitar la cancelacion mientras el pedido no haya sido entregado al transportista. Cuando corresponda un reembolso, la tienda lo inicia al mismo medio de pago dentro de **{$refundDays} dias habiles**. El tiempo en que el abono aparece depende de la entidad financiera y puede alcanzar 30 dias calendario.

Los servicios de envio ya ejecutados y documentados por causas atribuibles al cliente pueden no ser reembolsables. Esta regla no se aplica cuando la tienda o el transportista sean responsables.

## 7. Cambios, devoluciones y productos no conformes

No se aceptan devoluciones por cambio de opinion despues de la entrega. Si el producto llega defectuoso, danado, vencido o es distinto al solicitado, la tienda ofrecera la solucion que corresponda y asumira los costos de recojo, reposicion y nuevo envio cuando sea responsable.

Se recomienda comunicar incidentes visibles dentro de **{$incidentHours} horas** para facilitar su investigacion. Superar ese plazo no extingue los derechos que correspondan al consumidor.

Abrir el producto para descubrir un defecto no invalida el reclamo. Se solicita conservar, cuando sea razonablemente posible, el envase, numero de lote, fecha de vencimiento, contenido restante y evidencia fotografica.

## 8. Comprobantes

El cliente elige boleta o factura durante el checkout y debe proporcionar informacion exacta. El comprobante se emite con los datos fiscales confirmados y se remite al correo fiscal indicado.

## 9. Atencion y reclamos

Canales de atencion: **{$email}**, WhatsApp **{$whatsapp}**. Horario: **{$hours}**. Libro de Reclamaciones: **{$complaints}**.

## 10. Datos personales

El tratamiento de datos necesario para gestionar cuentas, compras, entrega, facturacion y soporte se explica en la Politica de privacidad. La aceptacion de estos terminos no autoriza por si sola el envio de publicidad.

## 11. Version y ley aplicable

La version aceptada al confirmar una compra queda asociada al pedido. Las nuevas versiones rigen hacia el futuro y no alteran las condiciones historicas. Se aplica la legislacion peruana y los derechos reconocidos al consumidor.
MARKDOWN;
    }

    /** @param array<string, int|string> $snapshot */
    private function privacy(array $snapshot): string
    {
        $tradeName = $this->text($snapshot, 'trade_name', 'Tienda demostrativa');
        $provider = $this->text($snapshot, 'provider_name', 'Responsable pendiente de definir');
        $taxId = $this->text($snapshot, 'tax_id', 'RUC pendiente de definir');
        $address = $this->text($snapshot, 'fiscal_address', 'Domicilio pendiente de definir');
        $email = $this->text($snapshot, 'contact_email', 'Correo pendiente de definir');

        return <<<MARKDOWN
## 1. Responsable del tratamiento

El responsable del tratamiento es **{$provider}**, identificado con **{$taxId}**, con domicilio en **{$address}**, para la tienda **{$tradeName}**. Cuando el sitio se identifique como demostrativo, la informacion ingresada debe ser exclusivamente de prueba.

## 2. Datos tratados

Podemos tratar datos de identificacion y contacto, credenciales protegidas, direcciones, contenido del carrito, pedidos, datos fiscales, comunicaciones de soporte y datos tecnicos indispensables para seguridad y funcionamiento.

La informacion completa de tarjetas es procesada por el proveedor de pago y no se almacena en la tienda.

## 3. Finalidades

Los datos se utilizan para crear y proteger la cuenta, verificar el correo, conservar el carrito, procesar pedidos, coordinar entregas o recojos, emitir comprobantes, atender consultas y reclamos, prevenir fraude y cumplir obligaciones legales.

Las comunicaciones publicitarias requieren una autorizacion separada, libre y revocable. Aceptar una compra o estos documentos no activa publicidad automaticamente.

## 4. Encargados y destinatarios

La informacion puede ser tratada por proveedores indispensables de infraestructura, correo, almacenamiento, pagos y entrega, dentro de las finalidades informadas y bajo medidas de seguridad razonables. No se vende informacion personal.

## 5. Conservacion

Los datos se conservan durante el tiempo necesario para prestar el servicio, mantener la trazabilidad de pedidos y comprobantes, atender controversias y cumplir los plazos legales aplicables. Los registros historicos sujetos a obligaciones contables o fiscales no se eliminan mientras deban conservarse.

## 6. Derechos del titular

El titular puede solicitar informacion, acceso, actualizacion, rectificacion, cancelacion u oposicion cuando corresponda. Tambien puede retirar consentimientos opcionales sin afectar tratamientos necesarios o realizados previamente de forma legitima.

Las solicitudes se reciben en **{$email}**. Para proteger al titular puede requerirse verificar su identidad antes de atenderlas.

## 7. Seguridad

Se aplican controles tecnicos y organizativos para reducir accesos no autorizados, perdida, alteracion o divulgacion. Ningun sistema es infalible; los incidentes se gestionaran conforme a la normativa aplicable.

## 8. Cookies y datos tecnicos

El sitio puede utilizar cookies de sesion y mecanismos equivalentes necesarios para autenticacion, carrito, seguridad y preferencias. Cualquier uso analitico o publicitario adicional se informara y gestionara de manera separada cuando corresponda.

## 9. Menores de edad

Las compras estan dirigidas a personas mayores de 18 anos. No se solicita intencionalmente informacion de menores para realizar pedidos.

## 10. Cambios de esta politica

Cada publicacion tiene una version y fecha. Una nueva version reemplaza a la anterior hacia el futuro sin modificar los registros historicos asociados a versiones previas.
MARKDOWN;
    }

    /** @param array<string, int|string> $snapshot */
    private function text(array $snapshot, string $key, string $fallback): string
    {
        $value = Str::squish((string) ($snapshot[$key] ?? ''));

        if ($value === '') {
            return $fallback;
        }

        return str_replace(['[', ']', '<', '>'], ['(', ')', '', ''], $value);
    }
}
