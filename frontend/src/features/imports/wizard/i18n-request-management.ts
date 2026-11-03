/**
 * Strings of the request-management import (spec 0348), merged into the
 * existing `importWizard` bundle and the default bundle by `./i18n.ts` so the
 * shared locale files stay untouched. `it` and `en` mirror each other 1:1.
 */

export const requestManagementWizardIt = {
  mapping: {
    attributeTarget: 'Informazioni aggiuntive: {{name}}',
  },
  domains: {
    'request-management': {
      pageTitle: 'Importa richieste',
      forbidden: 'Non hai i permessi per importare richieste.',
      targetHint:
        'Ogni colonna può alimentare un campo della richiesta, un’informazione aggiuntiva dell’offerta, essere salvata come campo extra oppure essere ignorata.',
      mappingSubtitle: 'Associa ogni colonna del file a un campo della richiesta, oppure ignorala.',
      configSubtitle: 'Questi valori si applicano a ogni richiesta importata. La Linea di prodotto decide stati e informazioni aggiuntive.',
      dedupHint: 'Decide cosa succede quando una riga del file corrisponde a un’anagrafica già esistente.',
      dedupModes: {
        create_new: 'Crea sempre una nuova anagrafica',
        update_existing: 'Usa l’anagrafica corrispondente e ne aggiorna i contatti',
        ignore: 'Salta le righe corrispondenti',
        manual: 'Decidi in revisione',
      },
    },
  },
  valueMapping: {
    title: 'Valori',
    subtitle: 'Abbina ogni valore del file a un elemento esistente. I valori non abbinati bloccano il proseguimento.',
    emptyValue: '(vuoto)',
    count: 'Presente in {{count}} righe',
    selectLabel: 'Abbinamento per «{{value}}»',
    placeholder: 'Seleziona…',
    createNew: 'Crea nuovo stato',
    newName: 'Nome del nuovo stato per «{{value}}»',
    newGroup: 'Gruppo del nuovo stato per «{{value}}»',
    groups: {
      open: 'Aperto',
      pending: 'In attesa',
      validated: 'Validato',
      closed_won: 'Chiuso con esito positivo',
      closed_lost: 'Chiuso con esito negativo',
    },
    nameErrors: {
      required: 'Inserisci il nome del nuovo stato.',
      exists: 'Esiste già uno stato con questo nome: sceglilo dall’elenco.',
    },
    needsProductLine: 'Scegli la Linea di prodotto nella sezione Configurazione globale qui sopra: serve per abbinare gli stati del file.',
    loading: 'Caricamento dei valori del file…',
    loadError: 'Impossibile caricare i valori del file.',
    unmatched_one: 'Manca l’abbinamento di {{count}} valore: completalo per continuare.',
    unmatched_other: 'Mancano gli abbinamenti di {{count}} valori: completali per continuare.',
  },
}

export const requestManagementWizardEn: typeof requestManagementWizardIt = {
  mapping: {
    attributeTarget: 'Additional information: {{name}}',
  },
  domains: {
    'request-management': {
      pageTitle: 'Import requests',
      forbidden: 'You do not have permission to import requests.',
      targetHint:
        'Each column can feed a request field, an additional offer information, be saved as an extra field or be ignored.',
      mappingSubtitle: 'Match each file column to a request field, or ignore it.',
      configSubtitle: 'These values apply to every imported request. The Product line decides statuses and additional information.',
      dedupHint: 'Decides what happens when a file row matches an existing registry record.',
      dedupModes: {
        create_new: 'Always create a new registry record',
        update_existing: 'Use the matching registry record and update its contacts',
        ignore: 'Skip matching rows',
        manual: 'Decide in review',
      },
    },
  },
  valueMapping: {
    title: 'Values',
    subtitle: 'Match each file value to an existing item. Unmatched values block the next step.',
    emptyValue: '(empty)',
    count: 'Found in {{count}} rows',
    selectLabel: 'Match for “{{value}}”',
    placeholder: 'Select…',
    createNew: 'Create new status',
    newName: 'Name of the new status for “{{value}}”',
    newGroup: 'Group of the new status for “{{value}}”',
    groups: {
      open: 'Open',
      pending: 'Pending',
      validated: 'Validated',
      closed_won: 'Closed won',
      closed_lost: 'Closed lost',
    },
    nameErrors: {
      required: 'Enter the name of the new status.',
      exists: 'A status with this name already exists: pick it from the list.',
    },
    needsProductLine: 'Choose the Product line in the Global configuration section above: it is needed to match the file statuses.',
    loading: 'Loading the file values…',
    loadError: 'Unable to load the file values.',
    unmatched_one: '{{count}} value is still unmatched: complete it to continue.',
    unmatched_other: '{{count}} values are still unmatched: complete them to continue.',
  },
}

type FieldId =
  | 'full_name'
  | 'first_name'
  | 'last_name'
  | 'company_name'
  | 'tax_code'
  | 'vat_number'
  | 'email'
  | 'phone'
  | 'street'
  | 'postal_code'
  | 'country'
  | 'region'
  | 'province'
  | 'city'
  | 'notes'
  | 'operator_name'
  | 'reporter_name'
  | 'workflow_status'
  | 'source_name'
  | 'next_callback_date'
  | 'product_name'
  | 'product_category_name'

const FIELDS_IT: Record<FieldId, string> = {
  full_name: 'Nome completo',
  first_name: 'Nome',
  last_name: 'Cognome',
  company_name: 'Ragione sociale',
  tax_code: 'Codice fiscale',
  vat_number: 'Partita IVA',
  email: 'Email',
  phone: 'Telefono',
  street: 'Indirizzo',
  postal_code: 'CAP',
  country: 'Nazione',
  region: 'Regione',
  province: 'Provincia',
  city: 'Comune',
  notes: 'Note',
  operator_name: 'Operatore',
  reporter_name: 'Segnalatore (referente)',
  workflow_status: 'Stato di lavorazione',
  source_name: 'Fonte',
  next_callback_date: 'Data richiamo',
  product_name: 'Prodotto',
  product_category_name: 'Linea di prodotto',
}

const FIELDS_EN: Record<FieldId, string> = {
  full_name: 'Full name',
  first_name: 'First name',
  last_name: 'Last name',
  company_name: 'Company name',
  tax_code: 'Tax code',
  vat_number: 'VAT number',
  email: 'Email',
  phone: 'Phone',
  street: 'Address',
  postal_code: 'Postal code',
  country: 'Country',
  region: 'Region',
  province: 'Province',
  city: 'City',
  notes: 'Notes',
  operator_name: 'Operator',
  reporter_name: 'Reporter (contact)',
  workflow_status: 'Workflow status',
  source_name: 'Source',
  next_callback_date: 'Callback date',
  product_name: 'Product',
  product_category_name: 'Product line',
}

/** Default-namespace labels the backend sends as `imports.request-management.*` keys. */
export const requestManagementLabelsIt = {
  imports: {
    'request-management': {
      fields: FIELDS_IT,
      global: {
        product_category_id: 'Linea di prodotto',
        operational_site_id: 'Sede operativa',
        source_id: 'Fonte di ripiego',
        operator_id: 'Operatore di ripiego',
      },
    },
  },
}

export const requestManagementLabelsEn: typeof requestManagementLabelsIt = {
  imports: {
    'request-management': {
      fields: FIELDS_EN,
      global: {
        product_category_id: 'Product line',
        operational_site_id: 'Operational site',
        source_id: 'Fallback source',
        operator_id: 'Fallback operator',
      },
    },
  },
}
