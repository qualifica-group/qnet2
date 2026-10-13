import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'agent-fiscal-profiles',
  title: 'Profili fiscali',
  summary: 'Ritenuta ed ENASARCO di ogni agente, con il periodo in cui valgono.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Contabilità › Provvigioni agenti › Profili fiscali**. Per ogni agente (un **referente**) raccoglie le impostazioni fiscali usate per liquidare le provvigioni: **ritenuta d’acconto** ed **ENASARCO**.',
        },
        {
          type: 'paragraph',
          text: 'Con **Nuovo profilo fiscale** ne crei uno; dalla riga puoi aprirlo, modificarlo o eliminarlo. Il profilo valido alla **data finale del periodo** di un lancio è quello usato per calcolare l’estratto dell’agente; senza un profilo valido l’agente viene saltato.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi del profilo',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Significato'],
          rows: [
            ['**Agente**', 'Il referente a cui si riferisce il profilo.'],
            ['**Valido dal / Valido fino al**', 'Il periodo di validità. Se **Valido fino al** è vuoto il profilo è aperto, cioè non ha una data di fine.'],
            ['**Applica la ritenuta**', 'Se attivo compaiono **Aliquota ritenuta (%)** e **Base imponibile (%)**, entrambi obbligatori, da 0 a 100 con al massimo 2 decimali. Se disattivo i due campi sono nascosti e non vengono salvati.'],
            ['**Applica ENASARCO**', 'Se attivo compare **Tipo mandato**, obbligatorio: **Monomandatario** o **Plurimandatario**. Se disattivo il campo è nascosto.'],
            ['**Aliquota IVA**', 'L’IVA aggiunta agli estratti dell’agente. Se la lasci vuota l’operazione è **senza IVA** (per esempio un agente in regime forfettario).'],
            ['**Note**', 'Testo libero, fino a 2000 caratteri.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Le aliquote e i massimali ENASARCO **non** si inseriscono nel profilo: sono nella pagina **Parametri ENASARCO**, uno per anno.',
        },
      ],
    },
    {
      id: 'validity',
      title: 'Periodi di validità',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per lo stesso agente i periodi **non possono sovrapporsi**, nemmeno quando uno è aperto. Se provi a salvare un periodo che si sovrappone a un altro, il campo **Valido dal** mostra l’errore e il profilo non viene salvato: chiudi prima il profilo precedente impostando **Valido fino al**.',
        },
        {
          type: 'tip',
          text: 'La colonna **In vigore** indica quali profili sono validi oggi.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
