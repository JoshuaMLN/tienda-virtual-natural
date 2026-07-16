<?php

namespace App\Support\Settings;

use App\Models\Setting;

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
        return $this->moneyToCents($this->freeShippingThreshold());
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

    public function shippingBanner(): string
    {
        if (! $this->freeShippingEnabled()) {
            return 'Entrega disponible solo en Lima Metropolitana y Callao';
        }

        $threshold = number_format((float) $this->freeShippingThreshold(), 2);

        return "Envio gratis en Lima y Callao por compras desde S/ {$threshold}";
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
