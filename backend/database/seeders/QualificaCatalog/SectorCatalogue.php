<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's EA sectors (spec 0213): code => name, in the client's own
 * order. Codes are strings kept verbatim — leading zeros and letter suffixes
 * (07a, 17b) are part of the code, and "NA" is a real row.
 */
final class SectorCatalogue
{
    /** @var array<string, string> */
    public const array SECTORS = [
        '01' => 'Agricoltura, silvicoltura e pesca',
        '02' => 'Industria mineraria e cave',
        '03' => 'Industrie alimentari, delle bevande e del tabacco',
        '04' => 'Tessuti e prodotti tessili',
        '05' => 'Cuoio e prodotti in cuoio',
        '06' => 'Legno e prodotti in legno',
        '07' => 'Pasta per carta, carta e prodotti in carta',
        '07a' => 'Prodotti in carta',
        '07b' => 'Produzione di cellulosa e carta',
        '08' => 'Case editrici',
        '09' => 'Tipografie',
        '10' => 'Fabbricazione di coke e di prodotti petroliferi raffinati',
        '11' => 'Energia nucleare',
        '12' => 'Chimica di base, prodotti chimici e fibre',
        '13' => 'Prodotti farmaceutici',
        '14' => 'Prodotti in gomma e materie plastiche',
        '15' => 'Prodotti minerali non metallici',
        '16' => 'Calce, gesso, calcestruzzo, cemento e prodotti affini',
        '17' => 'Metalli e prodotti in metallo',
        '17a' => 'Metalli di base',
        '17b' => 'Prodotti metallici fabbricati',
        '18' => 'Macchine ed apparecchiature',
        '19' => 'Apparecchiature elettriche ed ottiche',
        '20' => 'Costruzioni navali',
        '21' => 'Industria aerospaziale',
        '22' => 'Altri mezzi di trasporto',
        '23' => 'Produzione di manufatti (non classificata altrove)',
        '24' => 'Riciclaggio',
        '25' => 'Rifornimento di energia elettrica',
        '26' => 'Rifornimento di gas',
        '27' => 'Rifornimento di acqua',
        '28' => 'Costruzione',
        '29' => "Commercio all'ingrosso, al dettaglio; riparazione autoveicoli, motociclette e prodotti per la persona e la casa",
        '30' => 'Alberghi e ristoranti',
        '31' => 'Trasporti, logistica e comunicazioni',
        '32' => 'Intermediazione finanziaria, attività immobiliari, noleggio',
        '33' => "Tecnologia dell'informazione",
        '34' => 'Servizi d’ingegneria',
        '35' => 'Altri servizi',
        '36' => 'Pubblica amministrazione',
        '37' => 'Istruzione',
        '38' => 'Sanità ed altri servizi sociali',
        '39' => 'Altri servizi sociali',
        'NA' => 'AMMESSO CARICAMENTO SENZA SETTORE',
    ];
}
