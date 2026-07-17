<?php

namespace App\Support\Geography;

class LimaCallaoUbigeoCatalog
{
    /**
     * @return array<int, array{code: string, name: string, department: string, district_count: int}>
     */
    public function provinces(): array
    {
        $provinces = [];

        foreach ($this->coverage() as $code => $province) {
            $provinces[] = [
                'code' => (string) $code,
                'name' => $province['name'],
                'department' => $province['department']['name'],
                'district_count' => count($province['districts']),
            ];
        }

        return $provinces;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function districts(string $provinceCode): array
    {
        $province = $this->coverage()[$provinceCode] ?? null;

        if ($province === null) {
            throw new InvalidUbigeoException($provinceCode, '');
        }

        $districts = [];

        foreach ($province['districts'] as $ubigeo => $name) {
            $districts[] = [
                'code' => (string) $ubigeo,
                'name' => $name,
            ];
        }

        return $districts;
    }

    public function resolve(string $provinceCode, string $ubigeo): UbigeoLocation
    {
        $province = $this->coverage()[$provinceCode] ?? null;
        $district = $province['districts'][$ubigeo] ?? null;

        if ($province === null || $district === null || ! str_starts_with($ubigeo, $provinceCode)) {
            throw new InvalidUbigeoException($provinceCode, $ubigeo);
        }

        return new UbigeoLocation(
            departmentCode: $province['department']['code'],
            department: $province['department']['name'],
            provinceCode: $provinceCode,
            province: $province['name'],
            ubigeo: $ubigeo,
            district: $district,
        );
    }

    /**
     * @return array<int|string, array{
     *     name: string,
     *     department: string,
     *     districts: array<int, array{code: string, name: string}>
     * }>
     */
    public function selectionCatalog(): array
    {
        $catalog = [];

        foreach ($this->provinces() as $province) {
            $catalog[$province['code']] = [
                'name' => $province['name'],
                'department' => $province['department'],
                'districts' => $this->districts($province['code']),
            ];
        }

        return $catalog;
    }

    /**
     * @return array<int|string, array{
     *     name: string,
     *     department: array{code: string, name: string},
     *     districts: array<string, string>
     * }>
     */
    private function coverage(): array
    {
        return config('ubigeo.coverage', []);
    }
}
