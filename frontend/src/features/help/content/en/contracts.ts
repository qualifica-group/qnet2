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
          text: "The **Program** action creates one or more work orders from the offer's product lines: see the **Programming work orders** section.",
        },
      ],
    },
    {
      id: 'program-work-orders',
      title: 'Programming work orders',
      blocks: [
        {
          type: 'paragraph',
          text: "**Program** opens a two-panel window: the **offer lines** on the left, the **work orders to create** (groups) on the right. A single **Create N work orders** saves all the groups together.",
        },
        {
          type: 'steps',
          items: [
            'Select one or more free lines on the left and press **New group**: Group 1 appears and the lines show the **G1** badge.',
            'To add more lines to a group, select them and use **Add to group**. A line belongs to one group only: if it is already in another one, it is **moved**.',
            "In each group fill in **Type**, **Start date**, **Supervisors** (at least one) and, if needed, the **Task template**. The bin button deletes a group and frees its lines.",
            'Press **Create N work orders**.',
          ],
        },
        {
          type: 'list',
          items: [
            '**One work order per line**: creates a group for each selected line (with no selection, for every free line).',
            '**Group by category**: creates a group per product category, plus one for the lines **without a category**.',
            '**Common values**: Type, Start date, Supervisors and Task template that **new** groups inherit; the Start date starts at today and can be changed. Changing them later does not touch existing groups, unless you use **Apply to all groups** (it never changes titles or lines).',
            '**Duplicate group**: copies the group four values, with no title and no lines.',
          ],
        },
        {
          type: 'note',
          text: 'Saving is **all or nothing**: if one group has an error, no work order is created. The group with the error opens and shows the field to fix. Lines already in another work order stay visible but cannot be selected; lines you leave free are not an error. Closing with unsaved groups asks for confirmation. At most 50 work orders at a time.',
        },
        {
          type: 'tip',
          text: 'The group **Title** is optional: if you leave it empty the work order gets the automatic **code - products** title (e.g. COM-0042 - Consulting + Audit), previewed in the field.',
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
