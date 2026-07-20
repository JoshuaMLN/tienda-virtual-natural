<?php

namespace Tests\Feature;

use App\Models\NonWorkingDay;
use App\Models\Setting;
use App\Support\Delivery\BusinessDayCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDayCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_it_starts_on_the_next_service_day_and_skips_global_closures(): void
    {
        $this->schedule(saturday: true, sunday: false);
        NonWorkingDay::factory()->create([
            'date' => '2026-07-22',
            'reason' => 'Inventario',
        ]);

        $range = $this->calendar()->estimate(
            1,
            3,
            CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Lima'),
        );

        $this->assertSame('2026-07-21', $range->from->toDateString());
        $this->assertSame('2026-07-24', $range->to->toDateString());
        $this->assertSame('del 21 al 24 de julio', $range->label());
        $this->assertSame('entre el 21 y el 24 de julio', $range->availabilityLabel());
    }

    public function test_saturday_and_sunday_only_count_when_their_complete_schedule_is_configured(): void
    {
        $friday = CarbonImmutable::parse('2026-07-24 17:00:00', 'America/Lima');

        $this->schedule(saturday: true, sunday: false);
        $this->assertSame('2026-07-25', $this->calendar()->estimate(1, 1, $friday)->from->toDateString());

        Setting::setValues([
            Setting::BUSINESS_HOURS_SATURDAY_OPEN => '',
            Setting::BUSINESS_HOURS_SATURDAY_CLOSE => '',
            Setting::BUSINESS_HOURS_SUNDAY_OPEN => '10:00',
            Setting::BUSINESS_HOURS_SUNDAY_CLOSE => '14:00',
        ]);

        $this->assertSame('2026-07-26', $this->calendar()->estimate(1, 1, $friday)->from->toDateString());

        Setting::setValues([
            Setting::BUSINESS_HOURS_SUNDAY_OPEN => '',
            Setting::BUSINESS_HOURS_SUNDAY_CLOSE => '',
        ]);

        $this->assertSame('2026-07-27', $this->calendar()->estimate(1, 1, $friday)->from->toDateString());
    }

    public function test_the_range_label_handles_single_dates_months_and_years(): void
    {
        $this->schedule(saturday: false, sunday: false);
        CarbonImmutable::setTestNow('2026-12-29 10:00:00');

        $single = $this->calendar()->estimate(1, 1);
        $crossYear = $this->calendar()->estimate(1, 4);

        $this->assertSame('30 de diciembre', $single->label());
        $this->assertSame('el 30 de diciembre', $single->availabilityLabel());
        $this->assertSame('del 30 de diciembre de 2026 al 4 de enero de 2027', $crossYear->label());
        $this->assertSame('entre el 30 de diciembre de 2026 y el 4 de enero de 2027', $crossYear->availabilityLabel());
    }

    private function schedule(bool $saturday, bool $sunday): void
    {
        Setting::setValues([
            Setting::BUSINESS_HOURS_WEEKDAYS_OPEN => '09:00',
            Setting::BUSINESS_HOURS_WEEKDAYS_CLOSE => '18:00',
            Setting::BUSINESS_HOURS_SATURDAY_OPEN => $saturday ? '09:00' : '',
            Setting::BUSINESS_HOURS_SATURDAY_CLOSE => $saturday ? '13:00' : '',
            Setting::BUSINESS_HOURS_SUNDAY_OPEN => $sunday ? '10:00' : '',
            Setting::BUSINESS_HOURS_SUNDAY_CLOSE => $sunday ? '14:00' : '',
        ]);
    }

    private function calendar(): BusinessDayCalendar
    {
        return app(BusinessDayCalendar::class);
    }
}
