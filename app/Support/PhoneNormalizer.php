<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNormalizer
{
    /**
     * Normalize an Indonesian phone number to canonical 62... digits.
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Nomor telepon tidak valid.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! str_starts_with($digits, '628') || strlen($digits) < 11 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Nomor telepon harus berupa nomor Indonesia yang valid.');
        }

        return $digits;
    }

    public static function isValid(string $phone): bool
    {
        try {
            self::normalize($phone);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
