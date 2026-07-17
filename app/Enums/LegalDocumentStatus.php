<?php

namespace App\Enums;

enum LegalDocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
            self::Replaced => 'Reemplazado',
        };
    }
}
