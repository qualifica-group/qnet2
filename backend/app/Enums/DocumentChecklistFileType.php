<?php

namespace App\Enums;

/**
 * File types a checklist item accepts; values match the ArchivioSmart API (spec 0403).
 */
enum DocumentChecklistFileType: string
{
    case Pdf = 'pdf';
    case Jpg = 'jpg';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Webp = 'webp';
    case Heic = 'heic';
    case Docx = 'docx';
    case Odt = 'odt';
    case Txt = 'txt';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
