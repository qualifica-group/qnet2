import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'agent-sales-report',
  title: 'Agent Sales Report',
  summary:
    'The Agent Sales Report lists quotes with customer, sales agent, reporter, stage and net revenue, lets you filter them and download them as three Excel files.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page lives in **Statistics › Agent Sales Report**. It shows the list of quotes right away, without searching first: it helps you see and download sales by agent, customer and period. It is read-only: nothing is created, edited or deleted from here.',
        },
        {
          type: 'list',
          items: [
            'You need the **Agent Sales Report › View** permission, assigned in **Configuration › System › Roles**: without it the menu entry does not appear and the page shows an access denied message.',
            'Everyone sees only the quotes they have access to in the **Quotes** module: the report never widens your visibility, and this applies to the downloaded files too.',
            'Click the **Quote no.** of a row to open the quote.',
          ],
        },
      ],
    },
    {
      id: 'columns',
      title: 'The columns',
      blocks: [
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['Quote no.', 'The quote code.'],
            ['Created on', 'The quote date.'],
            ['Accepted on', 'The date the quote entered a won state (closed won). It stays empty while the quote is not won; it is cleared if the quote goes back to a non-won state, and unchanged when it moves between two won states.'],
            ['Company, Address, Postal code, City, Phone, Email', 'The customer record: main address and contacts.'],
            ['Title', 'The quote title.'],
            ['Company site', 'The quote\'s company site.'],
            ['Managers', 'The quote\'s account managers.'],
            ['Stage', 'The quote\'s working status.'],
            ['Net revenue', 'The quote amount before VAT.'],
            ['Sales agent, Reporter', 'The people who sold and reported the quote.'],
          ],
        },
        {
          type: 'note',
          text: 'The old QNet Expiry date column is not there: products have no duration in months.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtering and searching',
      blocks: [
        {
          type: 'paragraph',
          text: 'Use the search at the top to look into the title and the quote number, the column filters (for example **Company**, **Sales agent**, **Stage**, **Accepted on**) and **saved filters** to find your usual combinations again. The number of quotes found appears in the bar above the table.',
        },
        {
          type: 'tip',
          text: 'The table and the exports use the same filters and the same sorting: what you see is what you download.',
        },
      ],
    },
    {
      id: 'exports',
      title: 'The three exports',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apply the filters you need.',
            'Open the three-dot menu of the bar and choose an entry under **Export**.',
            'For **Details** and **Detail lines** the Excel file starts downloading right away; for **Summary** pick the format and wait for it to be prepared.',
          ],
        },
        {
          type: 'table',
          headers: ['Entry', 'Content'],
          rows: [
            ['Summary', 'The visible columns of the table, one row per quote, in the same order.'],
            ['Details', 'A block for each quote: the quote data (with the Consulting and Institution amounts), the sale lines with category, product, quantity, price and commissions, and the commission total with the net amount.'],
            ['Detail lines', 'A flat list: one row for each sale line of the filtered quotes, with the supervisor, sales agent and reporter commissions and the net amount.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'In Details and Detail lines only sale lines count (not costs), and the net amount is the amount minus the supervisor, sales agent and reporter commissions.',
        },
        {
          type: 'warning',
          text: 'If too many quotes match the filters, Details and Detail lines do not start and a message appears: narrow the filters and try again.',
        },
      ],
    },
  ],
}

export default guide
