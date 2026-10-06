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
          text: 'The create form looks like the quote detail: the same sections and rows, **closed**. Open a row with its pencil, fill in the field and press **Done** to keep it (or **Revert** to put it back as it was). The **Code** is already suggested.',
        },
        {
          type: 'steps',
          items: [
            'Open **Quotes** and click **New quote** (or start from the opportunity record).',
            'In **Quote data**, pick the **Opportunity**: Commercial, Reporter, Supervisor, Account managers and Operational site are prefilled from it (you can change them).',
            'Fill in the other rows you need (see table).',
            'At the bottom, add the sold product rows on the **Offer** tab and the cost rows on the **Costs** tab: the grids are already open and the summary updates as you type.',
            'Click **Save** (top or bottom): QNet checks every field and creates the quote; errors show under the rows to fix.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields', 'Notes'],
          rows: [
            ['Quote data', '**Code**, **Title**, **Opportunity**', 'The opportunity cannot change after creation.'],
            ['Registry and contacts', '**Commercial**, **Reporter** (with their **Rewards**)', 'Prefilled from the opportunity.'],
            ['Team', '**Supervisor**, **Account managers**', 'Synced with the opportunity.'],
            ['Company and sites', '**Company**, **Company site**, **Operational site**', 'Pick the company first.'],
            ['Document and payment', '**Layout**, **Payment method**', 'The default layout is already suggested.'],
            ['Internal notes', '**Internal notes**', 'Never shown to the client.'],
          ],
        },
        {
          type: 'note',
          text: 'The **Additional information** appears as soon as an offer row has a product that requires it. Leaving without saving (Cancel, closing the panel, a link) asks for confirmation.',
        },
        {
          type: 'note',
          text: "Whoever you add as an **Account manager** receives the **You were assigned as Account Manager** notification (bell and email) with the quote's title and details. The link opens the quote, or the request in **Request management** when they cannot access Quotes. The managers the quote inherits from the opportunity when you create it without setting them receive it too; whoever receives it does not receive the opportunity notification. It is not sent to managers only moved to another position, nor to whoever saves.",
        },
        {
          type: 'tip',
          text: "The **Title** is optional: left blank, QNet uses the quote code followed by the products on its revenue lines (for example QUO-0042 - ISO 9001 + SOA) and updates it when the lines change. A title you type stays yours; clear the field to go back to the automatic title.",
        },
      ],
    },
    {
      id: 'editing-a-quote',
      title: 'Editing a quote',
      blocks: [
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
          type: 'note',
          text: "A field without a pencil is not editable by you: your role's permissions make it read-only, or it is chosen at creation only (Code, Opportunity). Registry, Referent, Source, Business functions and General notes come from the opportunity and stay read-only.",
        },
        {
          type: 'list',
          items: [
            'The **Offer** and **Costs** rows have a pencil each: the editing grid opens and the summary below shows the totals of what you are typing. If the new products bring more Additional information, it appears under the grid: fill it in the same save (required ones are needed to save).',
            '**Rewards** are edited from the **Reporter** row. Changing the Commercial, Reporter or Supervisor makes QNet update the rows\' commissions by itself.',
            'Changing the **Company** clears the **Company site** in the same save.',
          ],
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
            ['**Product** / **Code**', 'The chosen product.'],
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
            'On the **Offer** tab, pick only **Sellable** products (proposed price: sale price); on the **Costs** tab, only products **Usable as a cost** (proposed price: cost).',
            'Once a product is chosen, QNet prefills unit, price and VAT: you can edit them.',
            '**Additional description** adds text to the row, printable on the quote document.',
            "Each row's **Commissions** can only be opened and changed for that row.",
            'Every cost row has an **Associated product**: pick "None (generic cost)" or a row from the Products tab to attribute that cost to that sale. Deleting the associated product row sends the cost back to "None" automatically.',
            'At most 200 rows per tab.',
          ],
        },
        {
          type: 'paragraph',
          text: "Products are limited to the opportunity's categories; **Show all products** opens the full catalogue (the new category is added to the opportunity).",
        },
        {
          type: 'paragraph',
          text: 'The summary shows, in this order, **Expected revenue**, **Expected cost**, the **Commission Summary** and the **Expected margin** (net revenue minus net cost minus commissions), plus the **Product Typology Summary**. Percentage commissions are calculated on the product row\'s own margin (its net revenue minus the costs imputed to it, never below zero): the **Margin per product** block shows revenue, imputed cost, commissions and margin for each product row, plus a "Generic costs" row for unattributed costs. This block is visible only to users who can see commissions.',
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
          text: 'Moving the quote to a **Closed (won)** status generates the contract.',
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
  ],
}

export default guide
