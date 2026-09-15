/**
 * Stringhe proprie del modulo "Gestione Iscritti" (spec 0130): tutto il resto
 * (etichette di colonna, azioni, dashboard, ecc.) e' condiviso col namespace
 * `requestManagement`, che i due moduli riusano identico — vedi
 * `RequestModuleConfig`/`useRequestModule`. Solo titolo e messaggio di
 * accesso negato sono propri, perche' nominano il modulo per nome.
 */

export const enrolleeManagement = {
  title: 'Gestione Iscritti',
  forbidden: 'Non hai il permesso di visualizzare Gestione Iscritti.',
}
