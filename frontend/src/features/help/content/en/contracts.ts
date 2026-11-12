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
          text: "Opportunity, Quote, Contract and Work order records show the same sections in the same place. Here you find **Contract data** (Offer, Opportunity, Offer date) and **Context**, then **Registry and contacts** (Registry, Referent with its type, Commercial, Reporter) and **Rewards**, then **Team** (the quote's Supervisor and Account managers) and **Company and sites**, then **Document and payment** and **Lifecycle**. The quote's data are read only and changed from the quote; Title, service lines, Payment notes, Renewal, Expiry and Comments are edited here.",
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
            ['Open or Pending', 'Title, lines and data editable in place, **Change status**, **Validate contract** (only if never validated), **Program** and **Renew** (only if already validated), **Terminate contract**'],
            ['Closed (positive)', 'Title, lines and data editable in place, **Program**, **Renew**, **Terminate contract**, **Reopen contract**'],
            ['Closed (negative)', '**Reopen contract** (card is read only)'],
            ['"Sospeso"', '**Reopen contract**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Validate contract**: set the **Validation date** (not in the future). At least one product line is required: if the contract has none, add it from the card before validating. It does not appear on a contract that already has a validation date, even when its status is Pending (for example "Da programmare", "Programmato" or "In scadenza"): those contracts show **Program** instead, unless suspended.',
            '**Terminate contract**: set the **Termination date**, **Reason** and **Destination status**. With the **Cancelled** status every work order of the contract is closed, completed ones too, and stays closed if you reopen the contract. With any other status only the work orders still open are closed, and **Reopen contract** puts them back on the status they had. The work orders\' tasks do not change.',
            'Contract data: **Expiry**, **Renewal**, **Payment notes** and **Comments** are edited straight in the card while the contract is Open, Pending or Closed (positive) (Validated): press the pencil next to the field (or click the value), change it and press **Save** (Cancel, Esc or a click outside close without saving). Comments are written inside their box at the top of the card.',
            '**Reopen contract**: brings the contract back to working; if it was suspended, it returns to the previous status. Reopening a Closed (positive) or Closed (negative) contract also clears the validation date and author, so the contract can be validated again; the previous values stay in **Activity history**.',
          ],
        },
        {
          type: 'note',
          text: "The **Program** action creates one or more work orders from the offer's product lines: see the **Programming work orders** section. The **Renew** action opens a new period of the same contract: see the **Renewing the contract** section.",
        },
      ],
    },
    {
      id: 'edit-contract-in-force',
      title: 'Editing the contract in force',
      blocks: [
        {
          type: 'paragraph',
          text: "From the contract card you can change the **Title** and the **Lines** (the services) straight in place, like the other data, even when the contract is **Validated**. You need the Contracts **Edit** permission and an editable field for your role: when missing, the pencil does not appear. The allowed statuses are **Open**, **Pending** and **Validated**; a **Cancelled** or **Terminated** contract stays read only until you reopen it with **Reopen contract**.",
        },
        {
          type: 'steps',
          items: [
            'For the title, press the pencil next to the title at the top, type the new text and press **Save**. If you leave it empty the contract goes back to the automatic title.',
            'For the services, press the pencil on the **Offer lines** box: pick more products from the catalogue to add lines, change quantity, price, VAT and supplier, or remove a line, then press **Save**.',
          ],
        },
        {
          type: 'list',
          items: [
            'Changes are written to the source quote: totals and margins are recalculated, while the status of the contract and of the quote do not change.',
            '**Commissions** cannot be edited from here: they stay as set on the quote and their amounts are recalculated on the new lines.',
            'A line **assigned to a work order** (it shows "Assigned to COM-...") is locked: it cannot be changed or removed until the work order is deleted. The same rule applies from the quote and from Request management.',
          ],
        },
        {
          type: 'note',
          text: 'If the save is refused (for example because of a locked line) the message appears on the line and your changes stay in the edit box, so you can fix them.',
        },
      ],
    },
    {
      id: 'program-work-orders',
      title: 'Programming work orders',
      blocks: [
        {
          type: 'paragraph',
          text: "**Program** opens a two-panel window: the **offer lines** on the left, the **work orders to create** (groups) on the right. A single **Create N work orders** saves all the groups together. At the top the window shows the **Period** (Initial or Renewal N) the work orders are created in.",
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
          text: 'Saving is **all or nothing**: if one group has an error, no work order is created. The group with the error opens and shows the field to fix. Lines already in another work order **of the current period** stay visible but cannot be selected (after a renewal they are free again); lines you leave free are not an error. Closing with unsaved groups asks for confirmation. At most 50 work orders at a time.',
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
            'In the **Contracts** table select from 2 to 50 contracts that are **Closed won** or **already validated** (and not suspended).',
            'Press the **Schedule single work order** action (you need the schedule permission).',
            'In the window the lines are grouped by contract. Only the **revenue lines** of a branch with the setting on and **not yet in a work order** can be selected; the others stay visible but disabled.',
            'Pick the lines: they must all belong to the **same branch** and come from **at least two** different offers.',
            'Fill in the common fields: **Title** (optional), **Type**, **Start date**, **Supervisors** (optional) and, if needed, the **Task template**.',
            'Press the confirm button: **one work order** is created and opens right away.',
          ],
        },
        {
          type: 'note',
          text: 'Saving is **all or nothing**: if something is wrong no work order is created and the error appears on the lines field. A line already in another work order in the current period of its contract cannot be reused. If at least one selected contract cannot be programmed yet (not validated, for example **Da validare**, or suspended) or cannot be grouped (its offer has no line of a branch with **Multi-offer work order** on), the window still opens and shows a warning with the reason and the list of those contracts, with their customer. If at least two remain, the **Continue without these contracts** button leaves them out of this work order and loads the lines of the others (the grid selection does not change); otherwise close the window and choose other contracts in the grid.',
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
      id: 'renewal-periods',
      title: 'Renewing the contract',
      blocks: [
        {
          type: 'paragraph',
          text: "**Renew** opens a new period of the same contract (Renewal 1, Renewal 2...): with the same offer and the same lines you program more work orders. Prices and commissions stay those of the offer and the offer total does not change. The action is available in the same statuses as **Program**, even when the expiry date has already passed, and requires the same permission.",
        },
        {
          type: 'steps',
          items: [
            'Press **Renew** in the contract action bar (or from the row action in the Contracts list).',
            'Check **New expiry date** (required, later than the current one) and **New renewal date** (optional, not later than the expiry date). They are already proposed by moving the current dates by the length of the period; when the starting dates are missing, an expiry date 12 months from today is proposed.',
            'Press **Renew**: the contract takes the new dates and the current period becomes the new renewal. The contract status does not change.',
            'Use **Program** to create the work orders of the new period: in each period an offer line is programmed only once.',
          ],
        },
        {
          type: 'list',
          items: [
            'The work orders of the previous periods stay unchanged; when you edit an existing work order, the lines you add go into **its** period.',
            'In each work order the line is invoiced once and its commission accrues in full: the same product is invoiced again in the renewal work order.',
            'In the detail, the **Renewals** panel sits at the top, right under the action buttons: it shows the period **in force**, how many days are left to the expiry (red once it has passed, amber when it is close), the date to renew by and a bar with the share of the period already elapsed. Below it is the line of periods (**Initial**, **Renewal 1**, **Renewal 2**...): click a period to see its **From**, **To**, **Renewal date**, **Previous dates**, **Renewed by** (who and when) and the number of **Work orders**. In the Work orders list (and in the contract Work orders tab) the **Period** column shows Initial or Renewal N.',
          ],
        },
        {
          type: 'note',
          text: '**Undo renewal** appears only in the details of the latest renewal and only while its period has no work orders: after confirming, the contract dates go back to those before the renewal.',
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Default filters and category",
      blocks: [
        {
          type: 'paragraph',
          text: "When it opens, the **Contracts** page shows every record your profile lets you see, without an **Assignment** filter: if you may see everything you see everything, otherwise you see the contracts where you are **Supervisor** or **Account manager** (and those of your site or competence, when your profile grants it). In the **Advanced filters** panel **Parent category** starts empty, and empty stands for every parent category you are enabled on: if you are enabled on all of them you see the contracts of every category, otherwise only the contracts of your categories (with all their subcategories). Opening the field's list you find only the parent categories you can pick: all of them if you are enabled on everything, otherwise only yours. Pick one or more and press **Apply** to narrow it down: your choice is saved. The **Search** field also finds contracts by client (registry) name: part of the name is enough, for example \"napoli\".",
        },
        {
          type: 'paragraph',
          text: "The categories you are enabled on are the competence categories of your profile (the assignment scope). If you have no competence categories **Parent category** limits nothing. The filters never widen what your profile can see.",
        },
        {
          type: 'paragraph',
          text: "In the same panel there is also **Product category** (that category only). A contract falls under a category when it is among the **revenue rows** of the contract's quote.",
        },
        {
          type: 'paragraph',
          text: "The flexible-field columns appear at the end of the list when the category filter points to a single category: a single value in **Product category** or, if that is empty, a single current parent category in **Parent category** (for example ISO). Choosing a current parent category also brings in the records of the linked legacy categories (for example [OLD] ISO), which do not appear as separate options in the filter, and the columns are those of the current category first, then those of the legacy ones. With two different current parent categories you see the records of both but no columns; the same with an empty **Parent category**. To see only the records of a legacy category or only of a new one use **Product category**. A field with the same name in several categories can appear twice. They show the fields of the contract's originating quote, read-only: they can be sorted, filtered and exported, while the values are edited from the quote.",
        },
        {
          type: 'note',
          text: "The limit to your categories with an empty **Parent category** applies only on the **Contracts** page: the field does not appear inside a record. The export follows the active filters.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which contracts you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; the levels are cumulative and depend on the contract's quote. Contract permissions are independent of the Quotes ones." },
        { type: 'table', headers: ["Permission", "Visible contracts"], rows: [["**View**", "Those where you are the **Supervisor** or an **Account manager** of the contract's quote."], ["**View by site**", "Your own plus those whose quote belongs to your **Operating site**."], ["**View by competence**", "Your own plus those whose quote has a category in your assignment scope, in any site. With **Scope on all categories**, all of them."], ["**View all**", "All of them."]] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to a contract you cannot see shows an access error. Seeing a contract does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
      ],
    },
  ],
}

export default guide
