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
        {
          type: 'paragraph',
          text: "Opportunity, Quote, Contract and Work order records show the same sections in the same place. Here you find **Contract data** (Offer, Opportunity, Offer date) and **Context**, then **Registry and contacts** (Registry, Referent with its type, Commercial, Reporter) and **Rewards**, then **Team** (the quote's Supervisor and Account managers) and **Company and sites**, then **Document and payment** and **Lifecycle**. The quote's data are read only and changed from the quote; Payment notes, Renewal, Expiry and Comments are edited here.",
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
            ['Open or Pending', 'Data editable in place, **Change status**, **Validate contract**, **Terminate contract**'],
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
            'Contract data: **Expiry**, **Renewal**, **Payment notes** and **Comments** are edited straight in the card while the contract is Open or Pending: press the pencil next to the field (or click the value), change it and press **Save** (Cancel, Esc or a click outside close without saving). Comments are written inside their box at the top of the card.',
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
            "In each group fill in **Type**, **Start date** and, if needed, the **Supervisors** (optional) and the **Task template**. The bin button deletes a group and frees its lines.",
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
      id: 'program-multi-quote-work-order',
      title: 'Scheduling a single work order from several contracts',
      blocks: [
        {
          type: 'paragraph',
          text: 'If the product categories of the branch have the **Multi-offer work order** setting on, you can gather the lines of several won contracts, even from different customers, into **one single work order**.',
        },
        {
          type: 'steps',
          items: [
            'In the **Contracts** table select from 2 to 50 contracts that are **Closed won**.',
            'Press the **Schedule single work order** action (you need the schedule permission).',
            'In the window the lines are grouped by contract. Only the **revenue lines** of a branch with the setting on and **not yet in a work order** can be selected; the others stay visible but disabled.',
            'Pick the lines: they must all belong to the **same branch** and come from **at least two** different offers.',
            'Fill in the common fields: **Title** (optional), **Type**, **Start date**, **Supervisors** (optional) and, if needed, the **Task template**.',
            'Press the confirm button: **one work order** is created and opens right away.',
          ],
        },
        {
          type: 'note',
          text: 'Saving is **all or nothing**: if something is wrong no work order is created and the error appears on the lines field. A line already in another work order cannot be reused. If at least one selected contract is not yet in a **Closed (positive)** status (for example **Da validare**) or cannot be grouped (its offer has no line of a branch with **Multi-offer work order** on), the window still opens and shows which contracts, with their customer, must be removed from the selection.',
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Expiry and renewal',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Alert** column shows **Expiring soon** (expiry within 30 days) or **Renewal due** (renewal within 30 days); if both apply, **Expiring soon** takes priority. Contracts in **Closed (negative)** show no alerts. The **Product lines** column lists the products of the offer\'s revenue lines, each with its category in brackets (read-only). The **Parent category** column shows the macro-category of the offer\'s products, for example Formazione (read-only, filterable).',
        },
        {
          type: 'tip',
          text: 'Always fill in **Expiry** and **Renewal** in the contract card: without these dates, the alerts never appear.',
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which contracts you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; the levels are cumulative and depend on the contract's quote. Contract permissions are independent of the Quotes ones." },
        { type: 'table', headers: ["Permission", "Visible contracts"], rows: [["**View**", "Those where you are the **Supervisor** or an **Account manager** of the contract's quote."], ["**View by site**", "Your own plus those whose quote belongs to your **Operating site**."], ["**View all**", "All of them."]] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to a contract you cannot see shows an access error. Seeing a contract does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
      ],
    },
  ],
}

export default guide
