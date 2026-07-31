import type { TranslationResources } from '@/i18n/locales/en'
import { navigation } from './it-navigation'
import { personalData, personalDataFieldLabels } from './it-personal-data'
import { enums } from './it-enums'
import { companies } from './it-companies'
import { companySites } from './it-company-sites'
import { operationalSites } from './it-operational-sites'
import { imports } from './it-imports'
import { activityLog } from './it-activity-log'
import { attachments } from './it-attachments'
import { exports } from './it-exports'
import { table } from './it-table'
import { referents, referentTypes } from './it-referents'
import { registries } from './it-registries'
import { attributes, productCategories, products } from './it-products'
import { customFields } from './it-custom-fields'
import { sectors } from './it-sectors'
import { sources } from './it-sources'
import { vatRates } from './it-vat-rates'
import { paymentMethods } from './it-payment-methods'
import { tags } from './it-tags'
import { pipelineStatuses } from './it-pipeline-statuses'
import { projects } from './it-projects'
import { campaigns } from './it-campaigns'
import { leads } from './it-leads'
import { opportunities } from './it-opportunities'
import { productLines } from './it-product-lines'
import { opportunityStatuses } from './it-opportunity-statuses'
import { opportunityWorkflows } from './it-opportunity-workflows'
import { quoteStatuses } from './it-quote-statuses'
import { quotes } from './it-quotes'
import { contractStatuses } from './it-contract-statuses'
import { contracts } from './it-contracts'
import { commissionConfigurations } from './it-commission-configurations'
import { rewardTypes } from './it-reward-types'
import { rewardStatuses } from './it-reward-statuses'
import { rewardedReferents } from './it-rewarded-referents'
import { documentLayouts } from './it-document-layouts'
import { requestManagement } from './it-request-management'
import { notes } from './it-notes'
import { notifications } from './it-notifications'
import { leadImports } from './it-lead-imports'
import { businessFunctions } from './it-business-functions'
import { moduleStats, statsPanel } from './it-stats'
import { impersonation } from './it-impersonation'
import {
  usersColumnsEmployment,
  usersDetailEmployment,
  usersFormEmployment,
  usersFormEmploymentSections,
  usersFormTabs,
} from './it-users-employment'

export const it: TranslationResources = {
  common: {
    loading: 'Caricamento…',
    retry: 'Riprova',
    search: 'Cerca',
    notFound: 'Pagina non trovata',
    backToDashboard: 'Torna alla dashboard',
    comingSoon: 'Questa sezione non è ancora disponibile.',
    clear: 'Cancella',
    confirm: 'Conferma',
    cancel: 'Annulla',
    confirmTitle: 'Sei sicuro?',
    yes: 'Sì',
    no: 'No',
    back: 'Indietro',
    edit: 'Modifica',
    new: 'Nuovo',
    viewProfile: 'Vedi il profilo di {{name}}',
    /** Appended to the name when duplicating a record (row action "duplicate"); leading space by design. */
    copySuffix: ' (copia)',
  },
  config: {
    error: {
      title: "Impossibile avviare l'applicazione",
      description:
        "Non è stato possibile caricare la configurazione dell'applicazione. Verifica la connessione e riprova.",
      retry: 'Riprova',
    },
  },
  navigation,
  theme: {
    toggle: 'Cambia tema',
    light: 'Chiaro',
    dark: 'Scuro',
    system: 'Sistema',
  },
  actions: {
    view: 'Visualizza',
    edit: 'Modifica',
    delete: 'Elimina',
    duplicate: 'Duplica',
    activity: 'Attività',
    convertToOpportunity: 'Converti in Opportunità',
    documents: 'Documenti',
    notes: 'Note',
    impersonate: 'Impersona',
    layout: 'Layout attributi',
    generateWord: 'Scarica preventivo',
  },
  table,
  statsPanel,
  users: {
    stats: moduleStats.users,
    title: 'Utenti',
    subtitle: 'Sfoglia, filtra e gestisci gli utenti della tua applicazione.',
    forbidden: 'Non hai i permessi per visualizzare gli utenti.',
    columns: {
      id: 'ID',
      avatar: 'Avatar',
      name: 'Nome',
      email: 'Email',
      roles: 'Ruoli',
      locale: 'Lingua',
      is_active: 'Attivo',
      created_at: 'Creato il',
      user_type: 'Tipo',
      primary_address: 'Indirizzo principale',
      country: 'Paese',
      region: 'Regione',
      province: 'Provincia',
      city: 'Città',
      primary_contact: 'Contatti principali',
      ...usersColumnsEmployment,
    },
    detail: {
      title: 'Dettaglio utente',
      subtitle: "Visualizzazione in sola lettura dell'utente selezionato.",
      loadError: "Impossibile caricare l'utente. Riprova.",
      // Sezione Rapporto di lavoro in sola lettura (spec 0015).
      employment: usersDetailEmployment,
    },
    form: {
      tabs: usersFormTabs,
      newUser: 'Nuovo utente',
      avatarLabel: 'Avatar',
      createTitle: 'Crea utente',
      createSubtitle: 'Aggiungi un nuovo utente alla tua applicazione.',
      editTitle: 'Modifica utente',
      editSubtitle: "Aggiorna l'utente selezionato.",
      name: 'Nome',
      email: 'Email',
      locale: 'Lingua',
      roles: 'Ruoli',
      rolesPlaceholder: 'Seleziona i ruoli…',
      rolesSearch: 'Cerca ruoli…',
      rolesEmpty: 'Nessun ruolo trovato.',
      rolesError: 'Impossibile caricare i ruoli.',
      rolesRemove: 'Rimuovi ruolo',
      is_active: 'Attivo',
      isActiveHint: "Un account non attivo non può accedere.",
      password: "La password dell'account",
      newPassword: 'Nuova password',
      confirmPassword: 'Ripeti la password',
      passwordEditHint: 'Lascia vuoto per mantenere la password attuale.',
      save: 'Salva',
      saving: 'Salvataggio…',
      cancel: 'Annulla',
      created: 'Utente creato con successo.',
      updated: 'Utente aggiornato con successo.',
      deleted: 'Utente eliminato con successo.',
      nameRequired: 'Il nome è obbligatorio.',
      nameMax: 'Il nome può contenere al massimo 255 caratteri.',
      emailRequired: "L'email è obbligatoria.",
      emailInvalid: 'Inserisci un indirizzo email valido.',
      passwordMinLength: 'La password deve avere almeno 8 caratteri.',
      confirmPasswordRequired: 'Conferma la password.',
      passwordsDontMatch: 'Le password non coincidono.',
      genericError: 'Si è verificato un errore. Riprova.',
      deleteError: "Impossibile eliminare l'utente. Riprova.",
      deleteForbidden: 'Non puoi eliminare questo utente.',
      sections: {
        identity: {
          title: 'Anagrafica',
          description: "Dati identificativi della persona o dell'azienda.",
        },
        credentials: {
          title: 'Autenticazione',
          description: "Credenziali di accesso e lingua dell'interfaccia.",
        },
        access: {
          title: 'Ruoli e accessi',
          description: 'Ruoli assegnati; i permessi vengono ereditati dai ruoli.',
        },
        contacts: {
          title: 'Contatti',
          description: 'Recapiti telefonici ed email.',
        },
        addresses: {
          title: 'Indirizzi',
          description: 'Sedi e indirizzi di fatturazione.',
        },
        ...usersFormEmploymentSections,
      },
      // The personal-data card fields/sections (spec 0008), read by the role
      // field-permissions matrix (`fieldPermissionLabel('users', 'personal_data.*')`).
      personal_data: personalDataFieldLabels,
      // Campi del rapporto di lavoro (spec 0015): tab Profilo/Rapporto/Dati contrattuali.
      employment: usersFormEmployment,
    },
  },
  personalData,
  geo: {
    country: 'Paese',
    state: 'Regione',
    province: 'Provincia',
    city: 'Città',
    countryPlaceholder: 'Seleziona un paese',
    statePlaceholder: 'Seleziona una regione',
    provincePlaceholder: 'Seleziona una provincia',
    cityPlaceholder: 'Seleziona una città',
    empty: 'Nessuna opzione disponibile',
    error: 'Caricamento delle opzioni non riuscito.',
    search: 'Cerca',
    noMatch: 'Nessun risultato',
    retry: 'Riprova',
    // Scope geografico derivato (spec 0027 D-2): il livello più fine valorizzato.
    scope: {
      country: 'Nazionale',
      state: 'Regionale',
      province: 'Provinciale',
      city: 'Cittadino',
    },
  },
  roles: {
    title: 'Ruoli',
    subtitle: 'Sfoglia, filtra e gestisci i ruoli e i relativi permessi.',
    forbidden: 'Non hai i permessi per visualizzare i ruoli.',
    columns: {
      id: 'ID',
      name: 'Nome',
      permissions: 'Permessi',
      users_count: 'Utenti',
      created_at: 'Creato il',
    },
    detail: {
      title: 'Dettaglio ruolo',
      subtitle: 'Visualizzazione in sola lettura del ruolo selezionato.',
      loadError: 'Impossibile caricare il ruolo. Riprova.',
    },
    form: {
      newRole: 'Nuovo ruolo',
      createTitle: 'Crea ruolo',
      createSubtitle: 'Aggiungi un nuovo ruolo e scegli i suoi permessi.',
      editTitle: 'Modifica ruolo',
      editSubtitle: 'Aggiorna il ruolo selezionato e i suoi permessi.',
      name: 'Nome',
      permissions: 'Permessi',
      selectAll: 'Seleziona tutti',
      selectAllGlobal: 'Seleziona tutti i permessi',
      noPermissions: 'Nessun permesso disponibile da assegnare.',
      users: 'Membri',
      usersPlaceholder: 'Seleziona utenti…',
      usersSearch: 'Cerca utenti…',
      usersEmpty: 'Nessun utente trovato.',
      usersError: 'Impossibile caricare gli utenti.',
      usersRemove: 'Rimuovi membro',
      save: 'Salva',
      saving: 'Salvataggio…',
      cancel: 'Annulla',
      created: 'Ruolo creato con successo.',
      updated: 'Ruolo aggiornato con successo.',
      deleted: 'Ruolo eliminato con successo.',
      nameRequired: 'Il nome è obbligatorio.',
      nameMax: 'Il nome può contenere al massimo 255 caratteri.',
      genericError: 'Si è verificato un errore. Riprova.',
      deleteError: 'Impossibile eliminare il ruolo. Riprova.',
      deleteForbidden: 'Non puoi eliminare questo ruolo.',
      sections: {
        details: {
          title: 'Dettagli ruolo',
          description: 'Nome del ruolo e utenti a cui è assegnato.',
        },
        permissions: {
          title: 'Permessi',
          description:
            'Raggruppati per dominio. I permessi avanzati sono nella configurazione dedicata.',
        },
      },
      advanced: 'Configurazione avanzata',
      advancedActions: 'Azioni aggiuntive',
    },
    fieldPermissions: {
      title: 'Permessi campi',
      visible: 'Visibile',
      editable: 'Modificabile',
      required: 'Obbligatorio',
      empty: 'Nessun campo disponibile da configurare.',
      loadError: 'Impossibile caricare il catalogo dei campi. Riprova.',
      mandatory: 'Obbligatorio per creare il record — non restringibile da un ruolo.',
    },
  },
  companies: { ...companies, stats: moduleStats.companies },
  companySites: { ...companySites, stats: moduleStats.companySites },
  settings: {
    title: 'Impostazioni',
    subtitle: 'Gestisci le preferenze del tuo account.',
    sectionNavLabel: 'Sezioni impostazioni',
    avatarTitle: 'Avatar',
    avatarSubtitle: "Carica un'immagine del profilo mostrata in tutta l'app.",
    avatarUpdated: 'Avatar aggiornato con successo.',
    avatarRemoved: 'Avatar rimosso con successo.',
    profileTitle: 'Profilo',
    profileSubtitle: 'Aggiorna le tue informazioni personali e la lingua preferita.',
    name: 'Nome',
    email: 'Email',
    language: 'Lingua',
    localeEnglish: 'Inglese',
    localeItalian: 'Italiano',
    saveProfile: 'Salva modifiche',
    savingProfile: 'Salvataggio…',
    profileUpdated: 'Profilo aggiornato con successo.',
    systemSettings: {
      title: 'Impostazioni sistema',
      subtitle: 'Preferenze di sistema del gestionale.',
    },
    uiScale: {
      title: 'Risoluzione interfaccia',
      subtitle:
        "Trascina per rimpicciolire o ingrandire testi, layout e tabelle dell'intera app. 100% è la dimensione normale.",
      saved: 'Risoluzione aggiornata con successo.',
      reset: 'Ripristina default',
    },
    moduleOpenMode: {
      title: 'Modalità apertura moduli',
      subtitle:
        'Scegli come si aprono le schermate di creazione, modifica e visualizzazione dei moduli.',
      modeLabel: 'Modalità',
      modeModal: 'Solo modale',
      modePage: 'Solo pagina singola',
      modeCustom: 'Personalizzata',
      customHint: 'Imposta la modalità di apertura per ciascun modulo.',
      valueModal: 'Modale',
      valuePage: 'Pagina singola',
      perModuleAria: 'Modalità di apertura per {{module}}',
      saved: 'Modalità di apertura aggiornata con successo.',
      reset: 'Ripristina default',
    },
    passwordTitle: 'Password',
    passwordSubtitle: 'Modifica la password usata per accedere.',
    currentPassword: 'La password attuale',
    newPassword: 'Nuova password',
    confirmPassword: 'Ripeti la nuova password',
    changePassword: 'Cambia la password',
    changingPassword: 'In corso…',
    passwordChanged: 'Password aggiornata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    emailRequired: "L'email è obbligatoria.",
    emailInvalid: 'Inserisci un indirizzo email valido.',
    currentPasswordRequired: 'La password attuale è obbligatoria.',
    passwordMinLength: 'La password deve avere almeno 8 caratteri.',
    confirmPasswordRequired: 'Conferma la nuova password.',
    passwordsDontMatch: 'Le password non coincidono.',
    genericError: 'Si è verificato un errore. Riprova.',
  },
  notifications,
  avatar: {
    chooseImage: 'Scegli immagine',
    removeAvatar: 'Rimuovi',
    uploading: 'Caricamento…',
    invalidImage: "Scegli un'immagine JPEG, PNG, GIF o WebP.",
    imageTooLarge: "L'immagine può essere al massimo di 10 MB.",
    avatarUploadError: "Impossibile aggiornare l'avatar. Riprova.",
  },
  auth: {
    signInTitle: 'Accedi',
    signInSubtitle: 'Inserisci le tue credenziali per accedere.',
    email: 'Email',
    password: 'La tua password',
    signIn: 'Accedi',
    signingIn: 'Accesso in corso…',
    signOut: 'Esci',
    account: 'Account',
    invalidCredentials: 'Email o password non validi.',
    genericError: 'Si è verificato un errore. Riprova.',
    emailRequired: "L'email è obbligatoria.",
    emailInvalid: 'Inserisci un indirizzo email valido.',
    passwordRequired: 'La password è obbligatoria.',
    forgotPasswordLink: 'Password dimenticata?',
    forgotPasswordTitle: 'Reimposta la password',
    forgotPasswordSubtitle: 'Inserisci la tua email e ti invieremo un link di reset.',
    sendResetLink: 'Invia link di reset',
    sending: 'Invio in corso…',
    resetLinkSent: 'Se esiste un account con questa email, è stato inviato un link di reset.',
    backToSignIn: "Torna all'accesso",
    resetPasswordTitle: 'Imposta una nuova password',
    resetPasswordSubtitle: 'Scegli una nuova password per il tuo account.',
    newPassword: 'Nuova password',
    confirmPassword: 'Ripeti la password',
    resetPasswordSubmit: 'Reimposta password',
    resetting: 'Reimpostazione…',
    passwordResetSuccess: 'La tua password è stata reimpostata. Ora puoi accedere.',
    passwordsDontMatch: 'Le password non coincidono.',
    passwordMinLength: 'La password deve avere almeno 8 caratteri.',
    resetLinkInvalid: 'Questo link di reset non è valido o è scaduto. Richiedine uno nuovo.',
    tooManyRequests: 'Troppe richieste. Attendi un momento e riprova.',
  },
  authorization: {
    loadError: 'Impossibile caricare i permessi. Riprova.',
    fieldNotEditable: 'Questo campo non può essere modificato.',
    moreInfo: 'Maggiori informazioni',
  },
  permissions: {
    abilities: {
      viewAny: 'Visualizza elenco',
      view: 'Visualizza',
      create: 'Crea',
      update: 'Modifica',
      delete: 'Elimina',
      // Oltre il CRUD di BasePolicy: l'atto da supervisore di assegnare
      // l'Operatore (GA2) in creazione (direttiva utente 2026-07-29).
      assignOperator: 'Assegna operatore',
    },
    resources: {
      users: 'Utenti',
      roles: 'Ruoli',
      addresses: 'Indirizzi',
      attachments: 'Allegati',
      contacts: 'Contatti',
      personal_data: 'Dati anagrafici',
      notes: 'Note',
    },
  },
  enums,
  businessFunctions,
  operationalSites: { ...operationalSites, stats: moduleStats.operationalSites },
  imports,
  activityLog,
  exports,
  referents: { ...referents, stats: moduleStats.referents },
  referentTypes,
  registries: { ...registries, stats: moduleStats.registries },
  attributes,
  customFields,
  productCategories: { ...productCategories, stats: moduleStats.productCategories },
  sectors,
  products: { ...products, stats: moduleStats.products },
  sources,
  vatRates,
  paymentMethods,
  tags,
  projects: { ...projects, stats: moduleStats.projects },
  pipelineStatuses,
  campaigns: { ...campaigns, stats: moduleStats.campaigns },
  leadImports,
  leads: { ...leads, stats: moduleStats.leads },
  opportunities: { ...opportunities, stats: moduleStats.opportunities },
  productLines,
  opportunityStatuses,
  opportunityWorkflows,
  quoteStatuses,
  quotes,
  contractStatuses,
  contracts,
  commissionConfigurations,
  requestManagement,
  rewardTypes,
  rewardStatuses,
  rewardedReferents,
  notes,
  importRuns: { stats: moduleStats.importRuns },
  attachments,
  impersonation,
  documentLayouts,
}
