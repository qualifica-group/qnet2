import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quotes',
  title: 'Quotes',
  summary: 'A quote is the economic proposal to the client, always linked to an opportunity.',
  sections: [
    {
      id: 'create-a-quote',
      title: 'Creating a quote',
      blocks: [
        {
          type: 'paragraph',
          text: 'The create form looks like the quote detail: the same sections and rows, **closed**. Open a row with its pencil, fill in the field and press **Done** to keep it (or **Revert** to put it back as it was). The **Code** is already suggested. The suggested code is provisional: if you keep it and another user takes it in the meantime, saving assigns the next free code.',
        },
        {
          type: 'steps',
          items: [
            'Open **Quotes** and click **New quote** (or start from the opportunity record).',
            'In **Quote data**, pick the **Opportunity**: Commercial, Reporter, Supervisor, Account managers and Operational site are prefilled from it (you can change them).',
            'Fill in the other rows you need (see table).',
            'At the bottom, add the sold product rows on the **Offer** tab, picking them from the **Product catalogue**, and the cost rows on the **Costs** tab: the editors are already open and the summary updates as you type.',
            'Click **Save** (top or bottom): QNet checks every field and creates the quote; errors show under the rows to fix.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields', 'Notes'],
          rows: [
            [
              'Quote data',
              '**Code**, **Title**, **Quote date**, **Opportunity**',
              "The quote date is proposed as today's date. The opportunity cannot change after creation.",
            ],
            ['Registry and contacts', '**Commercial**, **Reporter** (with their **Rewards**)', 'Prefilled from the opportunity.'],
            ['Team', '**Supervisor**, **Account managers**', 'Synced with the opportunity.'],
            ['Company and sites', '**Company**, **Company site**, **Operational site**', 'Pick the company first.'],
            ['Document and payment', '**Layout**, **Payment method**', 'The default layout is already suggested.'],
            ['Internal notes', '**Internal notes**', 'Never shown to the client.'],
          ],
        },
        {
          type: 'note',
          text: 'The **Additional information** appears, below the **Offer** and **Costs** rows, as soon as an offer row has a product that requires it. Some sections appear only when a yes/no field is on: with the field off the section disappears, its required fields are not needed and its values are cleared on save. Leaving without saving (Cancel, closing the panel, a link) asks for confirmation.',
        },
        {
          type: 'note',
          text: "Whoever you add as an **Account manager** receives the **You were assigned as Account Manager** notification (bell and email) with the quote's title and details. The link opens the quote, or the request in **Request management** when they cannot access Quotes. The managers the quote inherits from the opportunity when you create it without setting them receive it too; whoever receives it does not receive the opportunity notification. It is not sent to managers only moved to another position, nor to whoever saves.",
        },
        {
          type: 'tip',
          text: "The **Title** is optional: left blank, QNet uses the quote code followed by the products on its revenue lines (for example QUO-0042 - ISO 9001 + SOA) and updates it when the lines change. A title you type stays yours; clear the field to go back to the automatic title.",
        },
        {
          type: 'note',
          text: 'The **Quote date** is required and can also be changed from the detail. A quote created from **Request Management** or from a lead conversion takes the date of the day it is created.',
        },
      ],
    },
    {
      id: 'editing-a-quote',
      title: 'Editing a quote',
      blocks: [
        {
          type: 'paragraph',
          text: "Opportunity, Quote, Contract and Work order records show the same sections in the same place. Here you find **Quote data** and **Context**, then **Registry and contacts** and **Rewards**, then **Team** and **Company and sites**, then **Document and payment**. The Rewards section stays visible even when empty (\"No rewards\").",
        },
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a quote is edited **straight from its detail**, one field at a time, including the **Additional information** (each flexible field has its own row) and the **Offer** and **Costs** rows.',
        },
        {
          type: 'steps',
          items: [
            'Open the quote from the list.',
            'Hover the field to change and press its **pencil** (or click the value).',
            'Change the value in the control that appears.',
            "Press **Save** (or Enter in text fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it unchanged, without saving.",
          ],
        },
        {
          type: 'tip',
          text: "The **Internal notes** sit in the yellow box at the top of the detail, like the General notes in Request management: when empty the box invites you to write them; with the pencil (or a click on the text) you write them right inside the box. Right below, in a matching read-only box, you find the **Opportunity general notes**.",
        },
        {
          type: 'note',
          text: "A field without a pencil is not editable by you: your role's permissions make it read-only, or it is chosen at creation only (Code, Opportunity). Registry, Referent, Source, Product lines and General notes come from the opportunity and stay read-only.",
        },
        {
          type: 'list',
          items: [
            'The **Offer** and **Costs** rows have a pencil each: the editor opens with the catalogue and the rows, and the summary below shows the totals of what you are typing. If the new products bring more Additional information, it appears under the grid: fill it in the same save (required ones are needed to save).',
            '**Rewards** are edited from the **Reporter** row. Changing the Commercial, Reporter or Supervisor makes QNet update the rows\' commissions by itself.',
            'Changing the **Company** clears the **Company site** in the same save.',
          ],
        },
        {
          type: 'paragraph',
          text: "The **Recalculate commissions** button, top right next to the PDF, realigns the quote's commissions to the current rules of the Commission Configurator (for example after changing the direction of a Supplier rule). The summary shows how many rows, commissions and commissions changed by hand are involved; you must pick an answer, none is preselected: **Update only the rule-based commissions** (those changed by hand are kept, with their amount recalculated) or **Regenerate all from the rules** (those changed by hand are replaced too). The **Supplier commission calculation** switch of the product typology is read again too; margin and summary update at once. The button appears only if you may edit commissions and the quote is not closed.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Quick editing from the list",
      blocks: [
        {
          type: 'paragraph',
          text: "Clicking an editable cell (Title, Status, Commercial, Reporter, Supervisor, Account managers, Company, Company site, Operational site) edits it **directly in the list**, with the **same rules as the detail**: an invalid value or a field you cannot change is refused with a message and the cell goes back to its previous value.",
        },
        {
          type: 'note',
          text: "**Status** only offers the statuses of the quote's workflow and asks for the **note** when the chosen status requires it. Changing the **Company** clears the Company site; the Company site is picked among the row's company sites. Changing a role recalculates the line commissions as from the detail; an empty Title goes back to the automatic title.",
        },
        {
          type: 'paragraph',
          text: "Removing or adding people in **Account managers** keeps everyone else in their position. Code, Opportunity, Net revenue/cost/margin, Created at, Next callback, Alert and Product lines (the products of the revenue lines, with their category in brackets) and Parent category (the macro-category of the products, for example Formazione; filterable) stay read-only.",
        },
      ],
    },
    {
      id: 'offer-and-cost-lines',
      title: 'Offer rows and cost rows',
      blocks: [
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Product** / **Code**', 'The product chosen from the catalogue.'],
            ['**Quantity**', 'Greater than zero, at most 2 decimals.'],
            ['**Unit**', 'The unit of measure.'],
            ['**Unit price**', 'Non-negative, at most 2 decimals.'],
            ['**VAT**', "The row's VAT rate."],
            ['**Net**, **VAT**, **Total**', 'Calculated automatically.'],
          ],
        },
        {
          type: 'list',
          items: [
            'Each tab has the **Product catalogue** on the left and the rows (**Offer rows** / **Cost rows**) on the right; on narrow screens they stack. Search by name or description: each product shows name, code, typology, price with unit of measure (on the Costs tab the cost price), category path and description.',
            'Tick one or more products and press **Add N selected**: one row per product is created with quantity 1 and the product\'s price (sale or cost) and VAT, which you can edit. On the **Offer** tab the default commissions of each product are loaded too: if loading fails no row is added and the selection stays, so you can retry.',
            'Each row is a two-level card: product, code and typology; below, quantity × unit price, VAT and net/VAT/total. At the bottom you find the **Offer total** / **Cost total**. In the quote (and contract) detail the saved rows use the same card, read-only: product with typology and category, then quantity, unit price, VAT rate (and, for costs, the associated product) beside net/VAT/total, with the additional description below.',
            "If the product has suppliers, the row shows **Supplier** with the product suppliers : the default is already chosen and the field is required. On the **Offer** tab the row price is the chosen supplier's price and changes if you change supplier; next to the supplier the Supplier commission notice appears, if any (for example '10% commission paid'). On the **Costs** tab the price is the supplier's cost; if the supplier has the commission on that product the price is 0 and cannot be edited, with the same notice (that supplier's cost is the Supplier commission of the sale row).",
            "The **Supplier** commission goes to the supplier chosen on the row. If you change supplier, the commissions of the row are recalculated (if there are any, it asks for confirmation first). A commission rule dedicated to that supplier is applied automatically.",
            "Saved rows keep their own supplier even if the product changes its supplier list.",
            'To change the product of a row, remove the row and pick the new product from the catalogue.',
            'The **Costs** tab shows the whole catalogue of cost products, with no category filter, and the same product can be added more than once.',
            '**Additional description** adds text to the row, printable on the quote document.',
            "Each row's **Commissions** can only be opened and changed for that row. The popup shows the line net, imputed costs, calculation base and total commissions at the top; each role carries a label with the commission's origin (**Rule: Product**, **Rule: Category** or **Personal rule** when it comes from a Configurator rule, **Manual override** when it was changed by hand; hovering the label explains it). While editing, every role also shows the **System calculation** (what the Commission Configurator grants): if you changed the value by hand you can keep it or go back to the calculation with **Apply system calculation**. The **Supplier** row also shows the **Direction**: when the commission comes from a rule it is the rule's; when you change it by hand you choose **Received** (it is the line revenue) or **Paid** (a cost towards the supplier), and the margin updates accordingly.",
            'In the summary, under the cards, **Show commission details** opens (and **Hide commission details** closes) the **Commission details per product** block of the saved quote, which lists every row with commissions: for **Commercial**, **Reporter**, **Supervisor** and **Supplier** the recipient, the percentage (or fixed amount) and the amount; for the Supplier also the direction (**Received** or **Paid**), with a (?) explaining what it means. The name of the recipient leads to their record: hovering a user shows their profile card, hovering a referent or a registry shows the link to its record, which also opens by clicking the name (if you may view it). Hovering an amount shows its calculation: for a percentage the base (net amount minus the costs imputed to the row) and the rate applied, for the totals the balance. Each amount carries **+** when the commission comes in (received Supplier) and **−** when it goes out (Commercial, Reporter, Supervisor and paid Supplier). The last row and column give the totals as a balance: what comes in minus what goes out. It is visible only to users who can see commissions.',
            'Every cost row has an **Associated product**: pick "None (generic cost)" or a row from the Products tab to attribute that cost to that sale. Deleting the associated product row sends the cost back to "None" automatically.',
            'At most 200 rows per tab.',
          ],
        },
        {
          type: 'paragraph',
          text: "On the **Offer** tab the catalogue shows the products of the opportunity's categories, with the **All** chip and one chip per category. The **Whole catalogue** chip asks for confirmation and then shows the whole catalogue (the new category is added to the opportunity); clicking **All** or a category goes back to the filter. A product already in the rows is **Already added** and cannot be selected. If the opportunity is managed on a single category there are no checkboxes: clicking a product replaces the row's product (with confirmation if the commissions must be regenerated).",
        },
        {
          type: 'paragraph',
          text: 'The summary shows, in this order, **Expected revenue**, **Expected cost**, the **Commission Summary** (each amount carries **+** when the commission comes in and **−** when it goes out: Commercial, Reporter and Supervisor are always paid; the Supplier is **+** when received and **−** when paid, and a quote with both directions shows the two rows **Supplier (received)** and **Supplier (paid)**) and the **Expected margin** (sum of the line margins minus generic costs: a line with a **received** Supplier commission has the commission alone as revenue, the others are net amount minus costs and paid commissions; **Expected revenue** does not change), plus the **Product Typology Summary**. Percentage commissions are calculated on the product row\'s own margin (its net revenue minus the costs imputed to it, never below zero): the **Margin per product** block shows revenue, imputed cost, commissions and margin for each product row, so that revenue minus cost minus commissions gives the margin (lines with a received Supplier commission carry the note "revenue = received commission": the revenue is the received commission and the commissions are only the paid ones), plus a "Generic costs" row for unattributed costs. Hovering the product name shows it in full; every amount is underlined with dashes and, hovered or tapped, shows its calculation (for example 3,200.00 − 0.00 − 3,200.00 = 0.00). The block opens with **Show advanced data**, under the summary cards, and is visible only to users who can see commissions.',
        },
        {
          type: 'warning',
          text: 'A new quote must have at least one product row; if the opportunity is managed on a single category, only one sold row is allowed. In the table, the **Missing offer rows** alert flags quotes with no rows.',
        },
      ],
    },
    {
      id: 'change-the-quote-status',
      title: "Changing a quote's status",
      blocks: [
        {
          type: 'paragraph',
          text: 'The available statuses depend on the workflow configured in the **Offer status configurator**.',
        },
        {
          type: 'steps',
          items: [
            "On the quote detail, press the pencil of the **Status** row (in Quote data).",
            'Choose the new status.',
            'If the status is flagged **Note required**, write the **Note** that appears below (it is recorded among the notes of the opportunity).',
            'Click **Save**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Moving the quote to a **Closed (won)** status generates the contract. From then on the **Open contract** button at the top of the quote detail, and the **Open contract** entry in the more-actions menu (the three dots) of the row in the table, open the contract in a window over the quote. You see them only if your role may view Contracts.',
        },
      ],
    },
    {
      id: 'quote-pdf',
      title: 'Quote PDF',
      blocks: [
        {
          type: 'paragraph',
          text: "Open the quote (or use the table action) and click **Download quote**. QNet uses the quote's own **Layout** or, if missing, the active default layout for quotes.",
        },
        {
          type: 'tip',
          text: 'QNet does not email the quote: download the PDF and send it to the client with your usual tools.',
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which quotes you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; the levels are cumulative:" },
        { type: 'table', headers: ["Permission", "Visible quotes"], rows: [["**View**", "Those where you are the **Supervisor** or an **Account manager**."], ["**View by site**", "Your own plus those of your **Operating site** (physical or remote). A quote with no operating site only counts for its team."], ["**View all**", "All of them."]] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to a quote you cannot see shows an access error. Seeing a quote does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
        { type: 'note', text: "If you create a quote without being in its team, you will no longer see it unless you have **View all** or **View by site** (with the right operating site)." },
      ],
    },
  ],
}

export default guide
