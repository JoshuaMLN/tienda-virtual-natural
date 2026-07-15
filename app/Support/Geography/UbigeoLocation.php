<?php

namespace App\Support\Geography;

final readonly class UbigeoLocation
{
    public function __construct(
        public string $departmentCode,
        public string $department,
        public string $provinceCode,
        public string $province,
        public string $ubigeo,
        public string $district,
    ) {}

    /**
     * @return array{department: string, province: string, district: string, ubigeo: string}
     */
    public function toAddressAttributes(): array
    {
        return [
            'department' => $this->department,
            'province' => $this->province,
            'district' => $this->district,
            'ubigeo' => $this->ubigeo,
        ];
    }
}
