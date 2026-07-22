<?php

namespace App\Support\Fiscal;

final class FiscalIdentityDocumentMasker
{
    public function mask(string $document): string
    {
        $normalized = trim($document);
        $length = mb_strlen($normalized);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).mb_substr($normalized, -4);
    }
}
