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
          type: 'steps',
          items: [
            'Open **Quotes** and click **New quote** (or start from the opportunity record).',
            'Fill in the main data (see table).',
            'On the **Offer** tab, add the sold product rows.',
            'On the **Costs** tab, add the cost rows.',
            'On the **Notes and payments** tab, set the payment method and internal notes.',
            'Click **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields', 'Notes'],
          rows: [
            [
              'Quote data',
              '**Code**, **Title**, **Opportunity**, **Commercial**, **Reporter**',
              'Leave the code blank to generate it automatically.',
            ],
            ['Team', '**Supervisor**, **Account managers**', 'Synced with the opportunity.'],
            ['Company and sites', '**Company**, **Company site**, **Operational site**', 'Pick the company first.'],
            ['Document layout', '**Layout**', 'The template used for the PDF.'],
            ['Status', '**Status**, **Note**', 'See below.'],
            ['Notes and payments', '**Payment method**, **Internal notes**', 'Internal notes are never shown to the client.'],
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
          text: 'The summary shows **Expected revenue**, **Expected cost** and **Expected margin**, with **Net**, **VAT** and **Total**, plus the **Commission Summary**, the **Product Typology Summary** and the **Margin per product** block (revenue, imputed cost and margin per product row, plus a "Generic costs" row for unattributed costs).',
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
            'Open the quote for editing.',
            'In the **Status** section, choose the new status.',
            'If the status is flagged **Note required**, write the **Note** (it is recorded among the notes of the opportunity).',
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
