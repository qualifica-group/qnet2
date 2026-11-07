<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The open "trattative" of Corsi E-Campus, from the user's sheet
 * "Corsi E Campus_trattative 06_11_26.xlsx" (user directive 2026-11-07, same shape
 * as AplOrientationRequestCatalogue).
 *
 * User decision 2026-11-07: no offer line (the products are per year, the sheet
 * names only the degree course), the course goes to the notes; Informazioni
 * aggiuntive `degree_level` only where the course carries its class code
 * (L-xx = bachelor, LM-xx/LMG = master) and `faculty` only where the sheet fills
 * "Facolta". Cleaned at the source: nicknames completed from the email where plain
 * (Laurae Sartoris, Francesco Prato); the unreadable phone of Edoardo Tolu dropped
 * (note); the Moroccan number kept with its +212 prefix; "senas (lucia argiiuolo)"
 * -> the referent "Caivano CAF SENAS" already chosen for "senas" (APL sheet).
 */
final class ECampusRequestCatalogue
{
    public const string CATEGORY = 'Corsi E-Campus';

    private const string EDUCATION = "Corso di laurea: Scienze dell'Educazione - Università";

    private const string EDUCATION_SHORT = "Corso di laurea: Scienze dell'educazione";

    private const string L19 = "Corso di laurea: Scienze dell'educazione e della formazione L-19";

    private const string L8 = "Corso di laurea: Ingegneria Informatica e dell'automazione L-8";

    private const string L7 = 'Corso di laurea: Ingegneria Civile e Ambientale L-7';

    private const string L36 = 'Corso di laurea: Scienze Politiche e Sociali L-36';

    private const string CALLBACK = 'Da Richiamare';

    private const string PRE_ASSESSMENT = 'Attesa Prevalutazione';

    private const string BACHELOR = 'bachelor';

    private const string MASTER = 'master';

    /**
     * One entry per sheet row (2..45). Keys as AplOrientationRequestCatalogue, plus
     * degree_level and faculty (attribute option values).
     *
     * @var list<array<string, mixed>>
     */
    public const array ROWS = [
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Patrizia', 'last' => 'Leto', 'phones' => ['3496491472'], 'callback' => '2026-11-06', 'email' => 'patryleto1975@gmail.com', 'notes' => self::EDUCATION],
        ['operator' => 'Vincenzo Crisci', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Lucia', 'last' => 'Barbagallo', 'phones' => ['3491127955'], 'callback' => '2026-11-06', 'email' => 'luciabarbagallo@outlook.it', 'notes' => self::EDUCATION],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Edoardo', 'last' => 'Tolu', 'phones' => [], 'callback' => '2026-11-10', 'email' => 'edoardo.tolu58@gmail.com', 'notes' => 'Telefono nel file non valido: 112,85646202492984'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Rodolfo', 'last' => "D'Alonzo", 'phones' => ['3409446733'], 'callback' => '2026-11-27', 'email' => 'printupcsr@gmail.com', 'notes' => self::EDUCATION],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Alessandra', 'last' => 'Damasco', 'phones' => ['3381698837'], 'callback' => '2027-02-01', 'email' => 'alessandra.dama1979@libero.it'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Daniela', 'last' => 'Pasquale Lerose Palmieri', 'phones' => ['3493438733'], 'callback' => '2026-12-18', 'email' => 'danipaki88@libero.it', 'degree_level' => self::BACHELOR, 'notes' => self::L19],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Marinella', 'last' => 'Chianello', 'phones' => ['3316019336'], 'callback' => '2026-11-10', 'email' => 'slomofrancesco.63@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Monica', 'last' => 'Giannetti', 'phones' => ['3294043859'], 'callback' => '2026-11-09', 'email' => 'monicagiannetti@alice.it', 'degree_level' => self::BACHELOR, 'notes' => self::L19],
        ['operator' => 'Francesca Forgione', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Massimo', 'last' => 'Paolucci', 'phones' => ['3286477482'], 'callback' => '2026-11-06', 'email' => 'paolucci.maximo@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L8],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Laurae', 'last' => 'Sartoris', 'phones' => ['3499830887'], 'callback' => '2026-11-06', 'email' => '68laurae.sartoris@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L36],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Gian Luca', 'last' => 'Chiappa', 'phones' => ['3398803239'], 'callback' => '2026-11-09', 'email' => 'gianluca.chiappa.kap@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L36],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Teresa', 'last' => 'Sofia', 'phones' => ['3406067009'], 'callback' => '2027-02-01', 'email' => 'teresa.sofia@hotmail.it', 'degree_level' => self::MASTER, 'notes' => 'Corso di laurea: Scienze pedagogiche LM-85'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesca', 'last' => 'Carrella', 'phones' => ['3341652752'], 'callback' => '2026-11-09', 'email' => 'francesca.carrella@virgilio.it', 'degree_level' => self::BACHELOR, 'notes' => 'Corso di laurea: Scienze Biologiche L-13'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Paolo', 'last' => null, 'phones' => ['3356191171'], 'callback' => '2026-11-09', 'email' => 'pstsistemi@yahoo.com', 'degree_level' => self::BACHELOR, 'notes' => self::L7],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Angela', 'last' => 'Ferrulli', 'phones' => ['3451230705'], 'callback' => '2026-11-09', 'email' => 'info.gruppolachiave@gmail.com', 'notes' => self::EDUCATION],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Lavinia', 'last' => 'Tifeo', 'phones' => ['3911130103'], 'callback' => '2026-11-13', 'email' => 'acidalia.lannister@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L8],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Sara', 'last' => 'Brandino', 'phones' => ['3669725566'], 'callback' => '2026-11-27', 'email' => 'privacy.documenti8133e@gmail.com'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesco', 'last' => 'Castaldo', 'phones' => ['3516816158'], 'callback' => '2026-11-05', 'notes' => self::EDUCATION],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Laura', 'last' => 'Giola', 'phones' => ['3921361184'], 'callback' => '2027-01-07', 'email' => 'laura.giola88067@gmail.com', 'notes' => self::EDUCATION],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesca', 'last' => 'Iaia', 'phones' => ['3470858516'], 'callback' => '2026-11-09', 'email' => 'francaiaia39@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Claudia', 'last' => 'Di Lenardo', 'phones' => ['3488259990'], 'callback' => '2026-11-10', 'email' => 'claudia.dede@hotmail.it'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Alessandra', 'last' => 'Vita', 'phones' => ['3490614977'], 'callback' => '2026-11-09', 'email' => 'alevita@tiscali.it', 'degree_level' => self::BACHELOR, 'notes' => self::L19],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Gaetano', 'last' => 'Rizzo', 'phones' => ['3482468282'], 'callback' => '2026-11-09', 'email' => 'g.rizzo@oggipress.it', 'degree_level' => self::MASTER, 'notes' => 'Corso di laurea: Giurisprudenza LMG/01'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Rosalba', 'last' => 'Mercogliano', 'phones' => ['3394031506'], 'callback' => '2026-11-09', 'email' => 'rosalbamercogliano@gmail.com'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Omar', 'last' => 'Ubaldi', 'phones' => ['3895610238'], 'callback' => '2026-11-13', 'email' => 'omar.ub72@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Enzo', 'last' => 'Raciti', 'phones' => ['3922167653'], 'callback' => '2026-11-09', 'email' => 'e.racostruzionisrls@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L7],
        ['operator' => 'Simona Chiacchio', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Valentino', 'last' => 'Mascia Modestino', 'phones' => ['3245618650'], 'callback' => '2026-11-09', 'email' => 'vicesegremascia@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => 'Corso di laurea: Scienze e Tecniche Psicologiche L-24'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Evaristo', 'last' => 'Arnaldi', 'phones' => ['3472261288'], 'callback' => '2026-11-11', 'email' => 'evaristo.arnaldi@icloud.com'],
        ['operator' => 'Francesca Forgione', 'status' => self::PRE_ASSESSMENT, 'source' => 'Social', 'first' => 'Patrizia', 'last' => 'Martello', 'phones' => ['3338347571'], 'callback' => '2026-11-09', 'email' => 'martello.patrizia86@gmail.com', 'degree_level' => self::BACHELOR, 'notes' => self::L19],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Arlenis', 'last' => 'Duarte', 'phones' => ['3455002177'], 'callback' => '2026-11-05', 'email' => 'arleny.duarte@asl5.liguria.it', 'faculty' => 'psychology'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Spontaneo', 'company' => 'Ebanisteria La Terra', 'phones' => ['3382049719'], 'callback' => '2026-11-05'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Paola', 'last' => 'Ravaioli', 'phones' => ['3407932700'], 'callback' => '2026-11-05', 'email' => 'paola2cv@gmail.com'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Rita', 'last' => 'Capezzuti', 'phones' => ['3331014811'], 'callback' => '2026-11-09', 'email' => 'rita81.nicoletta@libero.it'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Tonino', 'last' => 'Catucci', 'phones' => ['3480068050'], 'callback' => '2026-11-09', 'email' => 'micheleantonio.catucci@gmail.com', 'notes' => self::EDUCATION_SHORT],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Eleonora', 'last' => 'Pintore', 'phones' => ['3341039535'], 'callback' => '2026-11-06', 'email' => 'elepinto86@gmail.com', 'notes' => self::EDUCATION_SHORT],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Gianfranco', 'last' => 'Canepa', 'phones' => ['3762796541'], 'callback' => '2026-11-06', 'email' => 'gianfranco.canepa.s@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Elia', 'last' => 'Rega', 'phones' => ['3485228081'], 'callback' => '2026-11-09', 'email' => 'dottrega@gmail.com'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Vanessa', 'last' => 'Fiore', 'phones' => ['3293345264'], 'callback' => '2026-11-05', 'email' => 'sibilla717@gmail.com'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Angelo', 'last' => 'Di Natale', 'phones' => ['3393425888'], 'callback' => '2026-11-09', 'email' => 'dinatale56_2014@libero.it'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Rabhi', 'last' => 'Salah', 'phones' => ['+212616444999'], 'callback' => '2026-11-06', 'email' => 'rabhisalah59@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Nicola', 'last' => 'Malagutti', 'phones' => ['3472990050'], 'callback' => '2026-11-11', 'email' => 'nicola.malagutti@hotmail.it', 'degree_level' => self::BACHELOR, 'notes' => 'Corso di laurea: Ingegneria industriale L-9'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesco', 'last' => 'Prato', 'phones' => ['3470991002'], 'callback' => '2026-11-09', 'email' => 'f.prato.95@gmail.com'],
        ['operator' => 'Simona Chiacchio', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Vincenzo', 'last' => 'Mascolo', 'phones' => ['3247484545'], 'callback' => '2026-11-09', 'email' => 'mascolov23@gmail.com'],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Concetta', 'last' => 'Lioniello', 'phones' => ['3351773917'], 'callback' => '2026-11-10', 'reporter' => 'Caivano CAF SENAS', 'notes' => 'Segnalatore nel file: senas (Lucia Argiiuolo)'],
    ];
}
