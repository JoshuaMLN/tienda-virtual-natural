<?php

namespace App\Support\Settings;

use App\Models\Setting;
use App\Support\Legal\PeruvianRuc;
use App\Support\Money\Money;

class StorefrontSettings
{
    public function whatsapp(): string
    {
        return Setting::string(Setting::CONTACT_WHATSAPP, '987654321');
    }

    public function whatsappDisplay(): string
    {
        $digits = preg_replace('/\D+/', '', $this->whatsapp()) ?? '';

        if (strlen($digits) === 9) {
            return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3);
        }

        return $this->whatsapp();
    }

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', $this->whatsapp()) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9) {
            $digits = '51'.$digits;
        }

        return 'https://wa.me/'.$digits;
    }

    public function email(): string
    {
        return Setting::string(Setting::CONTACT_EMAIL, 'hola@vitanatural.pe');
    }

    public function phone(): string
    {
        return Setting::string(Setting::CONTACT_PHONE, '(01) 123 4567');
    }

    public function weekdayHours(): string
    {
        return Setting::string(Setting::BUSINESS_HOURS_WEEKDAYS, 'Lunes a viernes: 9:00 am - 6:00 pm');
    }

    public function saturdayHours(): string
    {
        return Setting::string(Setting::BUSINESS_HOURS_SATURDAY, 'Sabado: 9:00 am - 1:00 pm');
    }

    public function freeShippingThreshold(): string
    {
        return Setting::decimal(Setting::FREE_SHIPPING_THRESHOLD, '149.00');
    }

    public function freeShippingThresholdCents(): int
    {
        return Money::fromDecimal($this->freeShippingThreshold())->cents;
    }

    public function freeShippingEnabled(): bool
    {
        return $this->freeShippingThresholdCents() > 0;
    }

    public function stockReservationMinutes(): int
    {
        return Setting::integer(Setting::STOCK_RESERVATION_MINUTES, 15);
    }

    public function deliveryBusinessDaysMin(): int
    {
        return max(1, Setting::integer(Setting::DELIVERY_BUSINESS_DAYS_MIN, 1));
    }

    public function deliveryBusinessDaysMax(): int
    {
        return max($this->deliveryBusinessDaysMin(), Setting::integer(Setting::DELIVERY_BUSINESS_DAYS_MAX, 2));
    }

    public function deliveryWindowLabel(): string
    {
        $minimum = $this->deliveryBusinessDaysMin();
        $maximum = $this->deliveryBusinessDaysMax();

        if ($minimum === $maximum) {
            return $minimum === 1 ? '1 dia habil' : "{$minimum} dias habiles";
        }

        return "{$minimum} a {$maximum} dias habiles";
    }

    public function pickupAddress(): string
    {
        return Setting::string(Setting::PICKUP_ADDRESS);
    }

    public function pickupEnabled(): bool
    {
        return $this->pickupAddress() !== '';
    }

    public function legalTradeName(): string
    {
        return Setting::string(Setting::LEGAL_TRADE_NAME, 'VitaNatural');
    }

    public function legalProviderName(): string
    {
        return Setting::string(Setting::LEGAL_PROVIDER_NAME);
    }

    public function legalTaxId(): string
    {
        return Setting::string(Setting::LEGAL_TAX_ID);
    }

    public function legalFiscalAddress(): string
    {
        return Setting::string(Setting::LEGAL_FISCAL_ADDRESS);
    }

    public function legalComplaintsBookUrl(): string
    {
        return Setting::string(Setting::LEGAL_COMPLAINTS_BOOK_URL);
    }

    public function liveSalesRequested(): bool
    {
        return Setting::integer(Setting::LIVE_SALES_ENABLED) === 1;
    }

    public function incidentReportHours(): int
    {
        return max(1, Setting::integer(Setting::INCIDENT_REPORT_HOURS, 48));
    }

    public function refundProcessingBusinessDays(): int
    {
        return max(1, Setting::integer(Setting::REFUND_PROCESSING_BUSINESS_DAYS, 5));
    }

    public function deliveryAttemptsPerCycle(): int
    {
        return max(1, Setting::integer(Setting::DELIVERY_ATTEMPTS_PER_CYCLE, 3));
    }

    public function deliveryMaxAutomaticCycles(): int
    {
        return max(1, Setting::integer(Setting::DELIVERY_MAX_AUTOMATIC_CYCLES, 2));
    }

    public function reshipmentPaymentDays(): int
    {
        return max(1, Setting::integer(Setting::RESHIPMENT_PAYMENT_DAYS, 7));
    }

    public function pickupHoldDays(): int
    {
        return max(1, Setting::integer(Setting::PICKUP_HOLD_DAYS, 14));
    }

    /** @return array<string, int|string> */
    public function legalSnapshot(): array
    {
        $snapshot = [
            'trade_name' => $this->legalTradeName(),
            'provider_name' => $this->legalProviderName(),
            'tax_id' => $this->legalTaxId(),
            'fiscal_address' => $this->legalFiscalAddress(),
            'complaints_book_url' => $this->legalComplaintsBookUrl(),
            'contact_email' => $this->email(),
            'contact_whatsapp' => $this->whatsapp(),
            'business_hours_weekdays' => $this->weekdayHours(),
            'business_hours_saturday' => $this->saturdayHours(),
            'incident_report_hours' => $this->incidentReportHours(),
            'refund_processing_business_days' => $this->refundProcessingBusinessDays(),
            'delivery_attempts_per_cycle' => $this->deliveryAttemptsPerCycle(),
            'delivery_max_automatic_cycles' => $this->deliveryMaxAutomaticCycles(),
            'reshipment_payment_days' => $this->reshipmentPaymentDays(),
            'pickup_hold_days' => $this->pickupHoldDays(),
        ];

        ksort($snapshot);

        return $snapshot;
    }

    /** @param array<string, int|string>|null $snapshot */
    public function legalFingerprint(?array $snapshot = null): string
    {
        return hash('sha256', json_encode(
            $snapshot ?? $this->legalSnapshot(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /** @return list<string> */
    public function missingLegalProfileFields(): array
    {
        $required = [
            'Nombre comercial' => $this->legalTradeName() !== '',
            'Razon social o titular' => $this->legalProviderName() !== '',
            'RUC valido' => PeruvianRuc::isValid($this->legalTaxId()),
            'Domicilio fiscal' => $this->legalFiscalAddress() !== '',
            'Libro de Reclamaciones' => filter_var($this->legalComplaintsBookUrl(), FILTER_VALIDATE_URL) !== false,
            'Correo de contacto' => filter_var($this->email(), FILTER_VALIDATE_EMAIL) !== false,
            'WhatsApp de contacto' => $this->whatsapp() !== '',
            'Horario de atencion' => $this->weekdayHours() !== '',
        ];

        return array_keys(array_filter($required, fn (bool $complete): bool => ! $complete));
    }

    public function shippingBanner(): string
    {
        if (! $this->freeShippingEnabled()) {
            return 'Entrega disponible solo en Lima Metropolitana y Callao';
        }

        $threshold = Money::fromDecimal($this->freeShippingThreshold())->formatted();

        return "Envio gratis en Lima y Callao por compras desde {$threshold}";
    }
}
