<?php

namespace App\Support\Orders;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class CustomerOrderDateFormatter
{
    public function compactDate(DateTimeInterface $date): string
    {
        return $this->local($date)->format('d/m/Y');
    }

    public function compactTime(DateTimeInterface $date): string
    {
        return str_replace(
            [' am', ' pm'],
            [' a. m.', ' p. m.'],
            strtolower($this->local($date)->format('h:i a')),
        );
    }

    public function descriptive(DateTimeInterface $date): string
    {
        $local = $this->local($date);

        return $local->translatedFormat('j \d\e F \d\e Y').' a las '.$this->compactTime($local);
    }

    public function dateRange(DateTimeInterface $from, DateTimeInterface $to): string
    {
        $start = $this->local($from)->startOfDay();
        $end = $this->local($to)->startOfDay();

        if ($start->isSameDay($end)) {
            return 'el '.$start->translatedFormat('j \d\e F \d\e Y');
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return 'del '.$start->format('j').' al '.$end->translatedFormat('j \d\e F \d\e Y');
        }

        if ($start->year === $end->year) {
            return 'del '.$start->translatedFormat('j \d\e F').' al '.$end->translatedFormat('j \d\e F \d\e Y');
        }

        return 'del '.$start->translatedFormat('j \d\e F \d\e Y').' al '.$end->translatedFormat('j \d\e F \d\e Y');
    }

    private function local(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)
            ->setTimezone(config('app.timezone'))
            ->locale(config('app.locale'));
    }
}
