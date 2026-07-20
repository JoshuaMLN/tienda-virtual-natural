<?php

namespace App\Support\Delivery;

use App\Models\NonWorkingDay;
use App\Support\Settings\StorefrontSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;

class BusinessDayCalendar
{
    private const MAX_SEARCH_DAYS = 730;

    public function __construct(
        private readonly StorefrontSettings $settings,
    ) {}

    public function estimate(
        int $minimumBusinessDays,
        int $maximumBusinessDays,
        ?DateTimeInterface $startsAt = null,
    ): EstimatedDateRange {
        if ($minimumBusinessDays < 1 || $maximumBusinessDays < $minimumBusinessDays) {
            throw new DomainException('El rango de dias de atencion no es valido.');
        }

        $timezone = (string) config('app.timezone', 'America/Lima');
        $start = CarbonImmutable::instance($startsAt ?? now())
            ->setTimezone($timezone)
            ->startOfDay();
        $searchEndsAt = $start->addDays(self::MAX_SEARCH_DAYS);
        $closures = NonWorkingDay::query()
            ->whereBetween('date', [$start->addDay()->toDateString(), $searchEndsAt->toDateString()])
            ->pluck('date')
            ->mapWithKeys(fn (mixed $date): array => [CarbonImmutable::parse((string) $date)->toDateString() => true])
            ->all();
        $cursor = $start;
        $workingDayNumber = 0;
        $estimatedFrom = null;
        $estimatedTo = null;

        for ($searched = 0; $searched < self::MAX_SEARCH_DAYS; $searched++) {
            $cursor = $cursor->addDay();

            if (! $this->isWorkingDay($cursor, $closures)) {
                continue;
            }

            $workingDayNumber++;

            if ($workingDayNumber === $minimumBusinessDays) {
                $estimatedFrom = $cursor;
            }

            if ($workingDayNumber === $maximumBusinessDays) {
                $estimatedTo = $cursor;
                break;
            }
        }

        if ($estimatedFrom === null || $estimatedTo === null) {
            throw new DomainException('No existe disponibilidad suficiente en el calendario de atencion.');
        }

        return new EstimatedDateRange($estimatedFrom, $estimatedTo);
    }

    /** @param array<string, bool> $closures */
    private function isWorkingDay(CarbonImmutable $date, array $closures): bool
    {
        return $this->settings->scheduleForIsoWeekday($date->dayOfWeekIso) !== null
            && ! isset($closures[$date->toDateString()]);
    }
}
