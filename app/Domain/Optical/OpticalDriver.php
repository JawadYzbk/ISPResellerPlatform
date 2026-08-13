<?php

namespace App\Domain\Optical;

use App\Models\OpticalDevice;

interface OpticalDriver
{
    public function supports(OpticalDevice $device): bool;

    /** @return array{onu_serial: string, rx_dbm: float|null, tx_dbm: float|null, temperature_c: float|null, recorded_at: string, source: string} */
    public function read(OpticalDevice $device, string $onuSerial): array;
}
