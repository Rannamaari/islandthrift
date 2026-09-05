<?php

namespace App\Services;

class MaldivesPhoneNormalizer
{
    public function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if (strlen($digits) === 7) {
            $digits = '960'.$digits;
        }

        return preg_match('/^960\d{7}$/', $digits) === 1 ? $digits : null;
    }

    /** @return array{valid: list<string>, invalid: list<string>} */
    public function normalizeMany(array|string $numbers): array
    {
        if (is_string($numbers)) {
            $numbers = preg_split('/[\s,;]+/', trim($numbers), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $valid = [];
        $invalid = [];

        foreach ($numbers as $number) {
            $original = trim((string) $number);
            $normalized = $this->normalize($original);

            if ($normalized === null) {
                if ($original !== '') {
                    $invalid[] = $original;
                }

                continue;
            }

            $valid[$normalized] = $normalized;
        }

        return ['valid' => array_values($valid), 'invalid' => array_values(array_unique($invalid))];
    }
}
