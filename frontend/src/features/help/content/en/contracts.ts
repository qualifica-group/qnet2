import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contracts',
  title: 'Contracts',
  summary: 'A contract is born when a quote moves to a Closed (positive) status.',
  sections: [
    {
      id: 'overview',
      title: 'How a contract is born',
      blocks: [
        {
          type: 'paragraph',
          text: "A contract is never created by hand: it is born when a quote moves to a **Closed (positive)** status, with an **Accepted at** date of that day and the **Default** status of **Contract Statuses**. It is not born if the product category is set to not generate contracts. If the quote leaves the positive status, the contract becomes **\"Sospeso\"** and QNet remembers the previous status.",
        },
      ],
    },
    {
      id: 'contract-actions',
      title: 'Actions on the contract',
      blocks: [
        {
          type: 'table',
          headers: ['Current status', 'Available actions'],
          rows: [
            ['Open or Pending', '**Edit data**, **Change status**, **Validate contract**, **Terminate contract**'],
            ['Closed (positive)', '**Program**, **Terminate contract**, **Reopen contract**'],
            ['Closed (negative)', '**Reopen contract**'],
            ['"Sospeso"', '**Reopen contract**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Validate contract**: set the **Validation date** (not in the future).',
            '**Terminate contract**: set the **Termination date**, **Reason** and **Destination status**.',
            '**Edit data**: update **Expiry date**, **Renewal date**, **Payment notes** and **Comments**.',
            '**Reopen contract**: brings the contract back to working; if it was suspended, it returns to the previous status.',
          ],
        },
        {
          type: 'note',
          text: 'The **Program** action generates a work order from the contract, assigning the chosen product lines to a new work order. The Work orders module is under development: its dedicated guide will be published once it is complete.',
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Expiry and renewal',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Alert** column shows **Expiring soon** (expiry within 30 days) or **Renewal due** (renewal within 30 days); if both apply, **Expiring soon** takes priority. Contracts in **Closed (negative)** show no alerts.',
        },
        {
          type: 'tip',
          text: 'Always fill in **Expiry date** and **Renewal date** with **Edit data**: without these dates, the alerts never appear.',
        },
      ],
    },
  ],
}

export default guide
