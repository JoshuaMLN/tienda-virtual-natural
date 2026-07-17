<?php

namespace Tests\Feature;

use App\Support\Geography\InvalidUbigeoException;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Tests\TestCase;

class LimaCallaoUbigeoCatalogTest extends TestCase
{
    public function test_catalog_documents_its_official_inei_source(): void
    {
        $this->assertSame(
            'Instituto Nacional de Estadistica e Informatica (INEI)',
            config('ubigeo.source.institution')
        );
        $this->assertSame('UBIGEO 2022 - 1891 distritos', config('ubigeo.source.dataset'));
        $this->assertStringContainsString('datosabiertos.gob.pe', config('ubigeo.source.url'));
    }

    public function test_catalog_exposes_lima_and_callao_with_expected_district_counts(): void
    {
        $provinces = $this->catalog()->provinces();

        $this->assertCount(2, $provinces);
        $this->assertSame([
            [
                'code' => '1501',
                'name' => 'Lima',
                'department' => 'Lima',
                'district_count' => 43,
            ],
            [
                'code' => '0701',
                'name' => 'Callao',
                'department' => 'Callao',
                'district_count' => 7,
            ],
        ], $provinces);
    }

    public function test_all_district_codes_are_unique_and_match_their_province(): void
    {
        $limaDistricts = $this->catalog()->districts('1501');
        $callaoDistricts = $this->catalog()->districts('0701');
        $allCodes = array_column([...$limaDistricts, ...$callaoDistricts], 'code');

        $this->assertCount(50, $allCodes);
        $this->assertCount(50, array_unique($allCodes));

        foreach ($limaDistricts as $district) {
            $this->assertMatchesRegularExpression('/^1501\d{2}$/', $district['code']);
        }

        foreach ($callaoDistricts as $district) {
            $this->assertMatchesRegularExpression('/^0701\d{2}$/', $district['code']);
        }
    }

    public function test_catalog_resolves_canonical_lima_and_callao_locations(): void
    {
        $surco = $this->catalog()->resolve('1501', '150140');
        $miPeru = $this->catalog()->resolve('0701', '070107');

        $this->assertSame('15', $surco->departmentCode);
        $this->assertSame('Lima', $surco->department);
        $this->assertSame('Lima', $surco->province);
        $this->assertSame('Santiago de Surco', $surco->district);
        $this->assertSame('150140', $surco->ubigeo);

        $this->assertSame('07', $miPeru->departmentCode);
        $this->assertSame('Callao', $miPeru->department);
        $this->assertSame('Callao', $miPeru->province);
        $this->assertSame('Mi Perú', $miPeru->district);
        $this->assertSame('070107', $miPeru->ubigeo);
    }

    public function test_selection_catalog_groups_districts_for_shared_address_forms(): void
    {
        $catalog = $this->catalog()->selectionCatalog();

        $this->assertSame([1501, '0701'], array_keys($catalog));
        $this->assertSame('Lima', $catalog['1501']['department']);
        $this->assertSame('Lima', $catalog['1501']['name']);
        $this->assertCount(43, $catalog['1501']['districts']);
        $this->assertSame('Callao', $catalog['0701']['department']);
        $this->assertSame('Callao', $catalog['0701']['name']);
        $this->assertCount(7, $catalog['0701']['districts']);
    }

    public function test_catalog_rejects_a_district_from_another_province(): void
    {
        try {
            $this->catalog()->resolve('1501', '070104');
            $this->fail('Expected invalid UBIGEO exception.');
        } catch (InvalidUbigeoException $exception) {
            $this->assertSame('1501', $exception->provinceCode);
            $this->assertSame('070104', $exception->ubigeo);
        }
    }

    public function test_catalog_rejects_locations_outside_the_enabled_coverage(): void
    {
        $this->expectException(InvalidUbigeoException::class);

        $this->catalog()->resolve('1502', '150201');
    }

    private function catalog(): LimaCallaoUbigeoCatalog
    {
        return new LimaCallaoUbigeoCatalog;
    }
}
