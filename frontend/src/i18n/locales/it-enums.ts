import { customFields } from './it-custom-fields'

/**
 * Localized labels for backend domain enums. Keyed by the snake_case enum key
 * (config/config.php → form_enums) then by the enum value. The frontend owns
 * these labels; the backend only supplies which values/colors/icons exist.
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Public API of `it.ts` is unchanged.
 */
export const enums = {
  // Catalogo dei tipi di campo condiviso da attributi e campi personalizzati
  // (config/custom-fields.php, non un enum PHP). Alias della copy di
  // `customFields`: una sola sorgente per le 13 etichette.
  custom_field_type: customFields.types,
  locale: {
    en: 'Inglese',
    it: 'Italiano',
  },
  personal_data_type: {
    individual: 'Persona fisica',
    company: 'Azienda',
  },
  lead_lifecycle_status: {
    not_associated: 'Non associato',
    associated: 'Associato',
    converted_to_opportunity: 'Convertito in opportunità',
  },
  gender: {
    male: 'Maschio',
    female: 'Femmina',
  },
  contact_type: {
    phone: 'Telefono',
    mobile: 'Cellulare',
    fax: 'Fax',
    email: 'Email',
    pec: 'PEC',
    website: 'Sito web',
  },
  notification_level: {
    info: 'Info',
    success: 'Successo',
    warning: 'Avviso',
    error: 'Errore',
  },
  // Profilo di impiego utente (spec 0015).
  relationship_type: {
    employee: 'Dipendente',
    self_employed: 'Partita IVA',
    other: 'Altro',
  },
  qualification_type: {
    employee_level_5: 'Impiegato 5° Liv.',
    administrative: 'Amministrativo',
    coordinator: 'Coordinatore',
    iso_consultant: 'Consulente ISO',
    teacher_cococo: 'Docenti Co.Co.Co.',
    teacher_vat: 'Docenti P.IVA',
    trainee_cost: 'Costo Tirocinante',
    hourly_cost_me: 'Costo orario M.E.',
  },
  // Ambito di contatto del referente (spec 0016).
  referent_contact_scope: {
    internal: 'Interno',
    external: 'Esterno',
  },
  // Classificazione del prodotto (spec 0017).
  product_type: {
    SERVICE: 'Servizio',
  },
  // Stato convenzione anagrafica (spec 0020).
  agreement_status: {
    negotiating: 'In trattativa',
    rejected: 'Respinta',
    agreed: 'Concordata',
  },
  // Classe dimensionale anagrafica (spec 0020).
  size_class: {
    micro: 'Micro',
    small: 'Piccola',
    medium: 'Media',
    large: 'Grande',
  },
  // Stato di una import run (badge dello storico import).
  import_status: {
    validating: 'In validazione',
    awaiting_confirmation: 'In attesa di conferma',
    analyzing: 'Analisi',
    configuring: 'Configurazione',
    staging: 'Preparazione',
    reviewing: 'Revisione',
    processing: 'Elaborazione',
    completed: 'Completato',
    failed: 'Fallito',
  },
}
