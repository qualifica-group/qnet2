<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "Categorie SOA" pick list of SoaCatalogue: the general (OG) and
 * specialised (OS) works categories, transcribed from the legacy
 * `soa_categories` table in its order, with the shouting labels lowered and
 * the stray spaces dropped. Kept apart only for its length.
 */
final class SoaCategoryOptions
{
    /**
     * @var list<array{value: string, label: string}>
     */
    public const array OPTIONS = [
        ['value' => 'og1', 'label' => 'OG1 Edifici civili e industriali'],
        ['value' => 'og2', 'label' => 'OG2 Restauro e manutenzione dei beni immobili sottoposti a tutela dei Beni Culturali'],
        ['value' => 'og3', 'label' => 'OG3 Strade, autostrade, ponti, viadotti, ferrovie, linee tranviarie, metropolitane, funicolari e piste aeroportuali, e relative opere complementari'],
        ['value' => 'og4', 'label' => 'OG4 Opere d\'arte nel sottosuolo'],
        ['value' => 'og5', 'label' => 'OG5 Dighe'],
        ['value' => 'og6', 'label' => 'OG6 Acquedotti, gasdotti, oleodotti, opere di irrigazione e di evacuazione'],
        ['value' => 'og7', 'label' => 'OG7 Opere marittime e lavori di dragaggio'],
        ['value' => 'og8', 'label' => 'OG8 Opere fluviali, di difesa, di sistemazione idraulica e di bonifica'],
        ['value' => 'og9', 'label' => 'OG9 Impianti per la produzione di energia elettrica (tipo impianti fotovoltaici e pale eoliche)'],
        ['value' => 'og10', 'label' => 'OG10 Impianti per la trasformazione alta-media tensione e per la distribuzione di energia elettrica (pubblica illuminazione)'],
        ['value' => 'og11', 'label' => 'OG11 Impianti tecnologici'],
        ['value' => 'og12', 'label' => 'OG12 Opere ed impianti di bonifica e protezione ambientale'],
        ['value' => 'og13', 'label' => 'OG13 Opere di ingegneria naturalistica'],
        ['value' => 'os1', 'label' => 'OS1 Lavori in terra'],
        ['value' => 'os2a', 'label' => 'OS2A Superfici decorate di beni immobili del patrimonio culturale e beni culturali mobili di interesse storico, artistico, archeologico ed etnoantropologico'],
        ['value' => 'os2b', 'label' => 'OS2B Beni culturali mobili di interesse archivistico e librario'],
        ['value' => 'os3', 'label' => 'OS3 Impianti idrico-sanitario, cucine, lavanderie'],
        ['value' => 'os4', 'label' => 'OS4 Impianti elettromeccanici trasportatori'],
        ['value' => 'os5', 'label' => 'OS5 Impianti pneumatici e antintrusione'],
        ['value' => 'os6', 'label' => 'OS6 Finiture di opere generali in materiali lignei, plastici, metallici e vetrosi'],
        ['value' => 'os7', 'label' => 'OS7 Finiture di opere generali di natura edile e tecnica'],
        ['value' => 'os8', 'label' => 'OS8 Opere di impermeabilizzazione'],
        ['value' => 'os9', 'label' => 'OS9 Impianti per la segnaletica luminosa e la sicurezza del traffico'],
        ['value' => 'os10', 'label' => 'OS10 Segnaletica stradale non luminosa'],
        ['value' => 'os11', 'label' => 'OS11 Apparecchiature strutturali speciali'],
        ['value' => 'os12a', 'label' => 'OS12A Barriere e protezioni stradali'],
        ['value' => 'os12b', 'label' => 'OS12B Barriere paramassi, fermaneve e simili'],
        ['value' => 'os13', 'label' => 'OS13 Strutture prefabbricate in cemento armato'],
        ['value' => 'os14', 'label' => 'OS14 Impianti di smaltimento e recupero rifiuti'],
        ['value' => 'os15', 'label' => 'OS15 Pulizia di acque marine, lacustri, fluviali'],
        ['value' => 'os16', 'label' => 'OS16 Impianti per centrali produzione energia elettrica'],
        ['value' => 'os17', 'label' => 'OS17 Linee telefoniche ed impianti di telefonia'],
        ['value' => 'os18a', 'label' => 'OS18A Componenti strutturali in acciaio'],
        ['value' => 'os18b', 'label' => 'OS18B Componenti per facciate continue'],
        ['value' => 'os19', 'label' => 'OS19 Impianti di reti di telecomunicazione e di trasmissione dati'],
        ['value' => 'os20a', 'label' => 'OS20A Rilevamenti topografici'],
        ['value' => 'os20b', 'label' => 'OS20B Indagini geognostiche'],
        ['value' => 'os21', 'label' => 'OS21 Opere strutturali speciali'],
        ['value' => 'os22', 'label' => 'OS22 Impianti di potabilizzazione e depurazione'],
        ['value' => 'os23', 'label' => 'OS23 Demolizione di opere'],
        ['value' => 'os24', 'label' => 'OS24 Verde e arredo urbano'],
        ['value' => 'os25', 'label' => 'OS25 Scavi archeologici'],
        ['value' => 'os26', 'label' => 'OS26 Pavimentazioni e sovrastrutture speciali'],
        ['value' => 'os27', 'label' => 'OS27 Impianti per la trazione elettrica'],
        ['value' => 'os28', 'label' => 'OS28 Impianti termici e di condizionamento'],
        ['value' => 'os29', 'label' => 'OS29 Armamento ferroviario'],
        ['value' => 'os30', 'label' => 'OS30 Impianti interni elettrici, telefonici, radiotelefonici e televisivi'],
        ['value' => 'os31', 'label' => 'OS31 Impianti per la mobilità sospesa'],
        ['value' => 'os32', 'label' => 'OS32 Strutture in legno'],
        ['value' => 'os33', 'label' => 'OS33 Coperture speciali'],
        ['value' => 'os34', 'label' => 'OS34 Sistemi antirumore per infrastrutture di mobilità'],
        ['value' => 'os35', 'label' => 'OS35 Interventi a basso impatto ambientale'],
    ];
}
