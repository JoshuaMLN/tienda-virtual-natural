<?php

namespace App\Support\Delivery;

use Carbon\CarbonImmutable;

final readonly class EstimatedDateRange
{
    private const MONTHS = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->dateLabel($this->from, $this->from->year !== now()->year);
        }

        if ($this->from->year !== $this->to->year) {
            return 'del '.$this->dateLabel($this->from, true).' al '.$this->dateLabel($this->to, true);
        }

        if ($this->from->month === $this->to->month) {
            return sprintf(
                'del %d al %d de %s%s',
                $this->from->day,
                $this->to->day,
                self::MONTHS[$this->to->month],
                $this->to->year !== now()->year ? ' de '.$this->to->year : '',
            );
        }

        return 'del '.$this->dateLabel($this->from).' al '.$this->dateLabel(
            $this->to,
            $this->to->year !== now()->year,
        );
    }

    public function availabilityLabel(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return 'el '.$this->dateLabel($this->from, $this->from->year !== now()->year);
        }

        if ($this->from->year !== $this->to->year) {
            return 'entre el '.$this->dateLabel($this->from, true).' y el '.$this->dateLabel($this->to, true);
        }

        if ($this->from->month === $this->to->month) {
            return sprintf(
                'entre el %d y el %d de %s%s',
                $this->from->day,
                $this->to->day,
                self::MONTHS[$this->to->month],
                $this->to->year !== now()->year ? ' de '.$this->to->year : '',
            );
        }

        return 'entre el '.$this->dateLabel($this->from).' y el '.$this->dateLabel(
            $this->to,
            $this->to->year !== now()->year,
        );
    }

    /** @return array{estimated_from: string, estimated_to: string, estimated_date_label: string} */
    public function toArray(): array
    {
        return [
            'estimated_from' => $this->from->toDateString(),
            'estimated_to' => $this->to->toDateString(),
            'estimated_date_label' => $this->label(),
        ];
    }

    private function dateLabel(CarbonImmutable $date, bool $includeYear = false): string
    {
        return sprintf(
            '%d de %s%s',
            $date->day,
            self::MONTHS[$date->month],
            $includeYear ? ' de '.$date->year : '',
        );
    }
}
