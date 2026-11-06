import type { HelpSection } from '../../../types'

/** Recurring-email section of the work-orders guide, split out to stay under the file-size limit. */
export const recurringEmailSections: HelpSection[] = [
    {
      id: 'recurring-emails',
      title: 'Email ricorrenti',
      blocks: [
        {
          type: 'paragraph',
          text: "Nella scheda **Email** della commessa puoi preparare una email una sola volta e farla **reinviare in automatico**, identica, a ogni data che scegli. Funziona solo per le email delle commesse.",
        },
        {
          type: 'steps',
          items: [
            'Premi **Nuova email** e compila destinatari, oggetto, corpo e allegati come al solito.',
            'Attiva l\'interruttore **Ripeti questa email**.',
            'Scegli la frequenza (giornaliera, settimanale, mensile, annuale), ogni quanti giorni, settimane, mesi o anni, e i giorni in cui inviare. Con **Solo giorni lavorativi** un invio che cadrebbe in un giorno festivo si sposta al primo giorno lavorativo successivo.',
            'Indica la data del **primo invio** e la **data di fine**: la data di fine è obbligatoria. Sotto i campi vedi la regola scritta in una frase.',
            'Premi **Programma** (al posto di Invia). L\'email non parte subito: il primo invio avviene nel giorno scelto.',
          ],
        },
        {
          type: 'note',
          text: "Gli invii partono alle **8:00** e sono **identici** all'email programmata: stessi destinatari, oggetto, corpo e allegati, senza ricalcolare i segnaposto del modello. Ogni invio compare nello storico come una normale email inviata, con un'icona di ripetizione.",
        },
        {
          type: 'paragraph',
          text: "La serie si **ferma da sola** quando arriva la data di fine, quando la commessa diventa **Terminata** o **Chiusa**, oppure se chi ha programmato l'email viene disattivato. Una email programmata mostra lo stato **Programmata**; a serie conclusa diventa **Programmazione terminata**, con il motivo nel dettaglio.",
        },
        {
          type: 'tip',
          text: "Per **interrompere o modificare** la serie apri l'email e premi **Riporta in bozza** (solo chi l'ha programmata). Torna una bozza con la regola già impostata: puoi cambiarla e riprogrammarla oppure eliminarla. Gli invii già fatti restano nello storico.",
        },
        {
          type: 'note',
          text: "Se un invio manca perché il sistema era fermo, non viene recuperato: alla ripartenza parte un solo invio e si prosegue dalla data successiva. Un invio non riuscito non ferma la serie e si può reinviare a mano dal suo dettaglio.",
        },
      ],
    },
]
