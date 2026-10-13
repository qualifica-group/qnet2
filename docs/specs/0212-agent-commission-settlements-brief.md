# Brief — Liquidazioni Agenti (provvigioni agenti)

> Input per `/plan-feature`. Modulo completo in 5 milestone, ognuna con una spec separata.
> La prima spec (M1) e' `0212-agent-commission-accruals.xml`.

## Cosa deve fare

Trasformare le provvigioni gia' calcolate sulle righe d'offerta (`quote_line_commissions`) in un debito pagabile
verso gli agenti, in modo massivo e per periodo. Non e' un terzo scadenziario: produce estratti conto per agente;
ogni estratto approvato genera una scadenza nello scadenziario passivo (spec 0211), dove avviene il pagamento.

Utenti: Contabilita' (lanci, estratti, rettifiche, pagamenti), Direzione (controllo), in M5 l'agente stesso
(sola lettura dei propri estratti).

## Comportamento atteso

- **Maturazione all'incasso**: una provvigione matura quando la fattura che contiene la riga d'offerta viene
  incassata, in proporzione all'incassato. Annullare un incasso storna il maturato.
- **Libro mastro**: ogni variazione e' un movimento con segno (`commission_accruals`); i movimenti gia' liquidati
  sono immutabili, le correzioni sono movimenti nuovi nel periodo successivo.
- **Ricalcolo idempotente**: rieseguire il calcolo sugli stessi dati non produce movimenti nuovi.
- **Liquidazione per periodo** (default mensile, periodo libero): un lancio crea un estratto in bozza per agente
  con il maturato non ancora liquidato; saldo negativo riportato al periodo successivo; soglia minima configurabile.
- **Ritenute**: netto = lordo + rettifiche - ENASARCO a carico agente - ritenuta d'acconto. Aliquote, basi e
  massimali mai nel codice.
- **Ciclo estratto**: DRAFT -> ISSUED -> DOCUMENT_RECEIVED (fattura/notula dell'agente registrata) -> APPROVED
  (genera la scadenza passiva) | CANCELLED. Un estratto approvato e' immutabile.

## Fatti verificati nel codice (2026-10-12)

- `quote_line_commissions`: unique [quote_line_id, recipient_role], `recipient` morph (`referent`|`user`|`registry`),
  `calculated_amount` decimal(15,2) calcolato con float da `CommissionCalculator` su base = margine riga.
  **Gli id NON sono stabili**: `QuoteLineCommissionWriter::replaceWithDefaults()` cancella e reinserisce; le righe
  d'offerta rimosse cancellano in cascata.
- `invoice_lines.quote_line_id` nullable nullOnDelete; le righe fattura vengono **ricreate a ogni update**.
- `invoice_installments`: un solo `collected_amount`/`collected_at` (date) per rata; un incasso parziale
  **riscrive `amount`** della rata e sposta il residuo (spread o nuova rata); `clear()` ripristina dallo snapshot e
  **cancella la rata residua**; senza incassi un update fattura ricrea tutte le rate (id nuovi).
- Nessun evento/observer sugli incassi: l'unico punto di aggancio sono `InvoiceCollectionService::record/clear`,
  `InvoiceBulkCollector::handle`, `InvoiceService::update/delete`.
- Proforma e fattura sono lo stesso documento (`invoices.type` cambia in place); la proforma ha rate e incassi.
  Nessuna nota di credito. Eliminazione fattura bloccata con incassi.
- Scadenziario passivo (spec 0211) presente nel working tree, approvato, non ancora committato: `purchase_invoices`
  (document_type proforma|invoice, supplier_id -> registries) + `purchase_invoice_installments` (paid_amount/paid_at),
  struttura speculare all'attivo. Il fornitore e' una `registry`: l'aggancio M4 dovra' risolvere agente -> registry.
- Nessun concetto di agente/ENASARCO nel codice; dati fiscali solo su `personal_data` (tax_code, vat_number).
  Nessuna tabella settings: i parametri configurabili stanno in `config/*.php` con default da env.
- Modulo Premi (spec 0059: `rewards`, `reward_types`, `reward_statuses`) separato, non si tocca.

## Dentro / fuori scope

Milestone (una spec ciascuna):
- **M1 Fondamenta**: profili fiscali agenti, libro mastro maturato, maturazione all'incasso con storno,
  ricalcolo idempotente, comando per lo storico.
- **M2 Liquidazione**: lanci massivi, estratti in bozza, rettifiche manuali, calcolo ritenute/ENASARCO.
- **M3 Documenti**: PDF estratto, email massiva, registrazione fattura/notula dell'agente, export.
- **M4 Passivo**: approvazione -> scadenza passiva collegata (sopra `purchase_invoices` di 0211).
- **M5 Visibilita'**: scheda agente, KPI dashboard, accesso agente ai propri estratti.

Fuori dal modulo: commissione SUPPLIER (D2), modulo Premi, contabilita' generale, F24/versamenti ritenute,
cedolini dei dipendenti (solo export, D7).

## Vincoli noti

- Stesso flusso degli altri moduli: migrazioni, model, policy e permessi, permessi di campo, FormRequest, service,
  resource, controller, API REST, AG Grid SSRM con filtri/ordinamento/export backend-driven, form create/edit,
  frontend React coerente.
- Importi in bcmath (come `InvoiceInstallment`/`InvoiceAmountCalculator`), decimal(15,2).
- Dati fiscali = dati sensibili: permessi di campo e review `security`.
- Nessuna nuova dipendenza.

## Decisioni proposte dall'utente (da confermare in fase domande)

- D1 Maturazione all'incasso della rata attiva, in proporzione all'incassato.
- D2 Agenti = COMMERCIAL, REPORTER, SUPERVISOR. SUPPLIER escluso in questa versione.
- D3 Emettiamo noi l'estratto; l'agente risponde con fattura o notula, che registriamo.
- D4 Periodo libero (default mensile).
- D5 Saldo negativo riportato al periodo dopo.
- D6 Soglia minima configurabile, default 0.
- D7 Liquidati qui solo i destinatari `referent`; i `user` interni solo come export per paghe.

## Domande aperte

Vedi la fase domande di `/plan-feature` (M1).
