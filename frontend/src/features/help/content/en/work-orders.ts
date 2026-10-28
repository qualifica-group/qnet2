import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Work orders',
  summary: 'The work to carry out on a won contract.',
  sections: [
    {
      id: 'editing-a-work-order',
      title: 'Editing a work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a work order is edited **directly from its detail**, one field at a time, **Additional information** included (every flexible field has its own row). A section tied to a yes/no field appears only while the field is on: switching it off clears the values of the section.',
        },
        {
          type: 'steps',
          items: [
            'Open the work order from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text and date fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it as it was, without saving.',
          ],
        },
        {
          type: 'tip',
          text: "The **Work order notes** sit in the yellow box at the top of the detail, like the General notes in Request management: when empty the box invites you to write them; with the pencil (or a click on the text) you write them right inside the box.",
        },
        {
          type: 'note',
          text: 'A field without a pencil cannot be edited by you: your role makes it read-only, or it is chosen only at creation (Work order no., Linked offer, Task template). Client registry, Contract (code and title), Company and sites come from the offer, as does the Contract expiry shown among the indicators at the top (edited on the contract); Status and Progress come from the progress records (see the dedicated section).',
        },
        {
          type: 'note',
          text: 'The **Product lines** are chosen when the work order is created and cannot be changed afterwards: in the detail you see them in the **Contract data** section. The Additional information the lines bring in is filled in at creation (the required ones are needed to save).',
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Quick editing from the list",
      blocks: [
        {
          type: 'paragraph',
          text: "Clicking an editable cell (Title, Work order type, Callback date, Start date, Supervisors) edits it **directly in the list**, with the **same rules as the detail**: for example the Title and the Start date are mandatory, while the Supervisors can all be removed; an invalid value is refused and the cell goes back to its previous value.",
        },
        {
          type: 'note',
          text: "**Force closing** stays a row action (it asks for a reason), not a cell. The **Registry** column shows the work order's client, taken from the linked offer: it can be filtered and sorted but not edited. The same goes for the **Contract expiry** column, the expiry date of the contract the work order comes from (date filter, empty when the contract has no expiry). Work order no., Contract no., Linked offer, Registry, Contract expiry, Status, Progress, Created at and Updated at stay read-only.",
        },
      ],
    },
    {
      id: 'force-close',
      title: 'Forced closure and reopening',
      blocks: [
        {
          type: 'paragraph',
          text: 'The forced closure is an **action**, not a field: you find it at the top of the work order detail and among the row actions of the Work orders table (also in the Contract\'s Work orders tab). It shows only if you may edit the work order.',
        },
        {
          type: 'steps',
          items: [
            'Press **Force close**.',
            'Write the **reason** (required). If the work order still has open tasks, the dialog tells you how many will be closed with a negative outcome.',
            'Press **Close work order**: the status becomes Closed and the reason appears among the work order details.',
          ],
        },
        {
          type: 'note',
          text: 'On a force-closed work order the action becomes **Reopen**: after the confirmation the closure is undone and its reason cleared. Tasks the forced closure closed stay closed.',
        },
      ],
    },
    {
      id: 'progress',
      title: 'Work order progress',
      blocks: [
        {
          type: 'paragraph',
          text: "Progress is recorded by hand: each record states the **percentage reached** (cumulative, from 1 to 100) and the **date**. The table's **Progress** column and the indicator at the top of the detail show the percentage and date of the latest record; with no records progress is 0%.",
        },
        {
          type: 'steps',
          items: [
            'Press **Work order progress**: you find it at the top of the work order detail, next to Force close, and among the row actions of the Work orders table. The popup with the records opens.',
            'Type the **percentage reached**: it must exceed the current one. The date is today, editable but not earlier than the latest record.',
            'Press **Record**. To fix a mistake delete the record and enter it again.',
          ],
        },
        {
          type: 'table',
          headers: ['Progress', 'Work order status'],
          rows: [
            ['0% (no records)', 'Open'],
            ['1% to 99%', 'In progress'],
            ['100%', 'Completed'],
            ['any, when force-closed', 'Closed'],
          ],
        },
        {
          type: 'note',
          text: 'You record and delete records if you may edit the work order. On a force-closed work order progress is read-only: reopen it to update it. Task statuses no longer change the work order status; the tasks percentage stays visible in the task board.',
        },
      ],
    },
    {
      id: 'creating-a-work-order',
      title: 'Creating a work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'The create form looks like the detail: the same sections and rows, **closed**. Open a row with the pencil, fill the field and press **Done** to keep it (or **Revert** to put it back as it was). The **Work order no.** is already suggested. The suggested number is provisional: if you keep it and another user takes it in the meantime, saving assigns the next free number.',
        },
        {
          type: 'note',
          text: 'In **Offer and product lines** choose the offer first, then its lines: the Additional information appears at the bottom as soon as the lines provide for it. **Save** at the top (or bottom) checks every field and creates the work order; errors appear under the rows to fix. Leaving without saving asks for confirmation.',
        },
      ],
    },
    {
      id: 'automatic-title',
      title: 'Automatic title',
      blocks: [
        {
          type: 'paragraph',
          text: 'The work order **Title** is optional. If you leave it empty, the work order gets the automatic **code - products of its lines** title (e.g. COM-0042 - ISO 9001 + SOA), which follows the lines when they change.',
        },
        {
          type: 'note',
          text: 'If you write your own title, it stays yours and is no longer recalculated. To go back to the automatic one, **clear the field** and save. Existing work orders keep the title they have. The same applies to the contract **Program** window.',
        },
      ],
    },
    {
      id: 'costs',
      title: 'Costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, below the main card, **Tasks** and **Costs** share one card as tabs you switch between: **Tasks** is open by default (or the first one you may see). The **Contract data** is not a tab: it sits in the main card, in the **Contract data** section. The **Costs** tab compares the **budgeted** costs (the offer cost lines imputed to the work order\'s revenue lines) with the **actual** costs entered here. It only appears if you may view costs.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['Variance', 'Actual cost minus budgeted cost: a positive value is an **overrun** (flagged with an icon and text, not by colour alone).'],
            ['Margin', 'Revenue of the work order lines minus costs. Revenue is the net amount, except on lines with a **received** supplier commission, where it is the supplier commission alone (as in the Contract data). Other commissions are excluded.'],
            ['Unattributed', 'Actual costs with no reference offer line: they are part of the total actual cost.'],
            ['Total actual cost', 'Sum, net of VAT, of three sources: the **manually entered costs**, the lines of the work order\'s **committed ODA** (approved, sent to supplier, partially or fully fulfilled) **not yet invoiced** (the committed amount of a line is the ordered quantity not yet billed times the unit price, or the received quantity if the residual is closed), and the lines of the work order\'s **passive invoices and proformas**. An ODA line that has been invoiced counts once, with the amount of the passive document. Drafts, ODA in validation and cancelled ones are excluded.'],
            ['Actual margin', 'Revenue minus the total actual cost; the total variance is the total actual cost minus the budgeted cost.'],
            ['By cost type', 'A table with, for each **cost type** (colour and nature: direct, indirect, general), the amount of the three sources and the total. Costs without a type are in the **No cost type** row. The budget is not split by cost type.'],
          ],
        },
        {
          type: 'note',
          text: 'Generic offer costs not imputed to any revenue line are shown separately as information and are not part of the comparison totals.',
        },
        {
          type: 'steps',
          items: [
            'Open the **Actual costs** tab: a single panel with the **Product catalogue** on the left and the actual costs as cards on the right (stacked on narrow screens). Without the permission to manage costs the catalogue is hidden and the cards are read-only.',
            'In the **Product catalogue** search by name or description: each product shows name, code, type, cost price with unit of measure, category and description.',
            'Tick one or more products and press **Add N selected**: each product becomes a cost with today\'s date, quantity 1, cost price, VAT, unit of measure and the product\'s **cost type**. The same product can be added more than once.',
            'Each card shows product, code and category at the top with the bin to remove it; below, quantity times unit price, VAT, **Reference offer line** and net amount, VAT and total; at the bottom date, **cost type**, **supplier**, **document reference** and extra description. The **Cost total** is at the bottom of the panel.',
            'To change product, remove the card and pick the product from the catalogue.',
            'Press **Save** to replace the whole list of costs, or **Cancel** to go back to the last saved state.',
          ],
        },
        {
          type: 'tip',
          text: 'Actual costs can also be entered on a completed or closed work order, since they often arrive after closure.',
        },
      ],
    },
    {
      id: 'contract-data',
      title: 'Contract data',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the main card of the Work order detail, the **Contract data** section shows for each product line the amounts already saved on the offer and **explains how the revenue comes about**. Without the permission to view contract data you only see the list of products. The product name is a **link** to the product. Each product line sits on **a single row** (code, name and typology side by side); on narrow screens each row becomes a compact card.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['Product', 'Code, name and typology of the product.'],
            ['Qty and Unit price', 'Quantity and price of the line.'],
            ['Net amount', 'Quantity times unit price, as saved on the offer.'],
            ['Supplier commission', 'The supplier commission on the line, if you can see it.'],
            ['Net of commissions', 'Net amount minus all the line\'s commissions, if you can see them.'],
            ['Effective revenue', 'If the line\'s typology has the Supplier commission **received** it is the Supplier commission alone; if it is **paid** or not calculated it is the net amount.'],
            ['Payment', 'Payment status (a badge in the status colour), an **Unpaid** badge when there are unpaid amounts; the payment agreement is in the cell tooltip.'],
            ['Actions', 'The pencil button opens the **Line payment** popup (only if you may manage payments).'],
          ],
        },
        {
          type: 'paragraph',
          text: 'The **how it is calculated** of each amount is in its tooltip: hover, use the keyboard (Tab) or tap the dashed-underlined amount to see the formula, for example "2 × 500.00 = 1,000.00" for the Net amount, "10% of 2,000.00 = 200.00" for the Supplier commission (with type, base = line margin and amount), "1,000.00 − 250.00 = 750.00" for the Net of commissions and the reason of the Effective revenue (received commission, or net amount). At the top a row of indicators: Net amount, Total revenue and, if visible, Commissions and Net of commissions, each with its tooltip; below, **Revenue by typology** with the net amount → revenue of each typology (those at 0.00 are dimmed). If you cannot see commissions, the related columns and indicators are not shown.'
        },
        {
          type: 'warning',
          text: 'A warning icon next to the product (with the text in its tooltip) appears when a line with a received Supplier commission has none (revenue 0.00) or when the commission was calculated on a previous base: save the offer again to update it.',
        },
        {
          type: 'steps',
          items: [
            'Press the pencil button at the end of the line (only available if you may manage payments): the **Line payment** popup opens.',
            'Choose the **payment status**, write the **payment agreement** and say whether there are **unpaid** amounts.',
            'Press **Save** to save the line and close the popup, or **Cancel** to discard the changes.',
          ],
        },
        {
          type: 'note',
          text: 'Payment statuses are configured in **Work order payment statuses**. When a line moves to a status flagged **Can be delivered**, the work order supervisors and participants are notified. Payment data can also be edited on a completed or closed work order.',
        },
      ],
    },
    {
      id: 'invoices',
      title: 'Active and passive proforma',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, in the same card as **Tasks** and **Costs**, the **Active proforma** and **Passive proforma** tabs list the documents tied to the work order. They only appear if you may view proforma and invoices (permission "View proforma and invoices"). They are **read-only**: nothing can be created, issued, collected or paid from here.',
        },
        {
          type: 'table',
          headers: ['Tab', 'What it shows'],
          rows: [
            ['Active proforma', 'The proforma and invoices issued to the customer for the work order: type, number, date, external number, customer, total, collected, residual and collection status, with a **Totals** row at the bottom.'],
            ['Passive proforma', 'The supplier proforma and invoices with **at least one line on the work order**: type, number, date, supplier, document total, **Work order share** (the sum of the document lines placed on this work order), paid and residual, with a **Totals** row.'],
          ],
        },
        {
          type: 'note',
          text: 'A proforma that gets registered stays in the table, now shown as an **Invoice**. A supplier document shows up as soon as one of its lines is on the work order, even if the other lines belong to other work orders: that is why the document total and the work order share can differ.',
        },
        {
          type: 'paragraph',
          text: 'The **number** of the document is a link: if you may view active invoices (or passive invoices) it opens the document in a side panel, where the usual actions remain subject to their own permissions; otherwise it is plain text.',
        },
      ],
    },
    {
      id: 'multi-quote-work-order',
      title: 'Multi-offer work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'A **multi-offer** work order gathers lines from several offers (and therefore several customers). It is created from the **Contracts** table with **Schedule single work order** (see the Contracts guide) and is recognised by the **Multi-offer** badge.',
        },
        {
          type: 'list',
          items: [
            'In the detail there are no customer, contract, company and site sections: each product line shows, under its name, the **registry** it refers to, linked to its record.',
            'After creation, lines can only be **removed** (at least one must remain): lines from other offers cannot be added.',
            'In the list the Customer and Offer columns show several values separated by " · "; filtering by one of the customers finds the work order. The contract expiry stays empty.',
            'The work order appears in the **Work orders** tab of the contract of each source offer.',
            'The cost budget is made of the cost lines of **all** the source offers.',
            'Tasks generated from the template have no customer.',
            'The Email tab has no customer or offer variables and the recipients are not pre-filled.',
          ],
        },
        {
          type: 'note',
          text: 'Proforma requests of a multi-offer work order are one per offer: see the Proforma Requests guide. An offer with lines in a multi-offer work order cannot be deleted.',
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Proforma request (the € button)',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you can create proforma requests, the **Actions** column of the Work orders list (also in the Work orders tab of a contract and of a registry) shows the **€** button. Its color is the state: **grey** no request yet, **blue** request sent and not yet fulfilled, **yellow** requests already fulfilled (proforma issued).',
        },
        {
          type: 'steps',
          items: [
            'Press the grey **€** button: the **Proforma issue request: Work order #number** window opens.',
            'Check the **Payment method** taken from the quote (**Not specified** appears when it is missing).',
            'Write the **Notes for Accounting** (required, up to 5000 characters): the text is prefilled with the window title.',
            'Press **Send request**. The system creates one request for the Consultancy lines and one for each supplier of the Institution lines.',
          ],
        },
        {
          type: 'note',
          text: 'With the blue **€** button the window shows **Last request on** and the date: until the request is fulfilled another one cannot be sent and **Send request** stays disabled. The yellow button opens nothing: the requests are already fulfilled and no new ones can be generated. A work order generates its requests only once.',
        },
        {
          type: 'tip',
          text: 'Sent requests are listed in **Accounting › Receivables › Proforma requests**.',
        },
      ],
    },
    {
      id: 'deleting-a-work-order',
      title: 'Deleting a work order',
      blocks: [
        {
          type: 'warning',
          text: 'A work order **cannot be deleted** if it has linked proforma requests, active invoices, purchase orders or purchase invoices: in that case the **Delete** button does not appear. Tasks, hours and manually entered costs do not block deletion.',
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which work orders you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; the levels are cumulative:" },
        { type: 'table', headers: ["Permission", "Visible work orders"], rows: [["**View**", "Those where you are a **Supervisor** or a **Participant**."], ["**View by site**", "Your own plus those whose quote belongs to one of your sites. For a work order with several quotes, one is enough."], ["**View all**", "All of them."]] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to a work order you cannot see shows an access error. Seeing a work order does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
        { type: 'note', text: "If you create a work order without being a Supervisor or a Participant, you will no longer see it unless you have **View all** or **View by site**." },
      ],
    },
    {
      id: 'in-development',
      title: 'Module in development',
      blocks: [
        {
          type: 'note',
          text: 'This module is under development. The guide will be published once the module is complete.',
        },
      ],
    },
  ],
}

export default guide
