import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'request-management',
  title: 'Request Management',
  summary:
    "Request Management is the daily work bench for customer requests: what they asked for, which products they're interested in and who is handling them.",
  sections: [
    {
      id: 'overview',
      title: 'What is Request Management',
      blocks: [
        {
          type: 'paragraph',
          text: 'It lives in **Opportunities and Work Orders › Request Management**. Each row of the list corresponds to a Quote linked to an Opportunity.',
        },
        {
          type: 'paragraph',
          text: 'At the top there is a tab for every product category, plus **All**: pick one to see only the requests of that category. The tabs that do not fit on the row move into **More (N)**, on the right: open it, type part of the name to filter the list and pick the category with a click or with the arrow keys and **Enter**. The chosen category always stays visible among the tabs.',
        },
        {
          type: 'paragraph',
          text: 'To keep the categories you use at hand, mark them as favorites: in the menu (**More (N)**, or the star to the right of the tabs when they all fit) tap the star next to the category. Favorites come first among the tabs and at the top of the menu. Turn on **Show favorites only** to keep only them among the tabs (plus the category you are viewing); the others stay in the menu. Favorites are saved on your user, even on another computer, and are separate for Request Management and Enrollee Management.',
        },
        {
          type: 'paragraph',
          text: 'The numbers on the tabs update within a few seconds of changes made by other users; your own changes show up right away when you return to the table.',
        },
        {
          type: 'paragraph',
          text: "The **Search** box finds requests by the client's first name, last name, tax code, VAT number, or primary phone or email. It starts from the third character and matches words that **begin** with what you type: \"ros\" finds Rossi, \"ssi\" does not. With several words (for example \"mario rossi\") all of them must match. One- and two-letter words are ignored. With very generic terms the list may be incomplete: add a word to narrow it down.",
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['Source', 'The channel the request comes from.'],
            ['Change requests', 'Change proposals still awaiting a decision.'],
            ['Product lines', 'Products present in the quote.'],
            ['Operator', 'The person working the request.'],
            ['Operational site', 'The site the request is assigned to.'],
            ['Next callback', 'Date of the next call to the client.'],
            ['Transferred', 'Whether the contact was transferred from another site.'],
            ['Working status', "The quote's working status."],
          ],
        },
        {
          type: 'paragraph',
          text: 'Click the **Product category** cell to change it without opening the request: pick the parent category, then the product category. If the current category is managed as a single row (for example Formazione), the one you pick replaces it; otherwise it is added to the others, and single-row categories cannot be picked (the same holds for the second row of the form). The **X** removes a category.',
        },
      ],
    },
    {
      id: 'creating-a-request',
      title: 'Create a new request',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New request**.',
            'In **General notes** write what the client asked for, in their own words.',
            'If needed, set the **Next callback** (the time is optional).',
            'In **Product lines** add at least one row: parent category, then product category.',
            "Fill in the quote's rows: product, quantity, unit price and VAT.",
            'In **Client details** pick an **Existing registry** or enter a new client.',
            'In **Attribution** pick the **Source** (required) and, if needed, **Reporter** and **Operational site**.',
            'Fill in the **Additional information**, if present.',
            'Press **Create request**: you go back to the table, on the category tab you had open.',
          ],
        },
        {
          type: 'tip',
          text: 'Picking an existing registry hides the identity, contacts and address fields and links the request to that client.',
        },
        {
          type: 'warning',
          text: 'The **Assigned rewards** can only be added after picking a **Reporter**.',
        },
      ],
    },
    {
      id: 'working-a-request',
      title: 'Work a request',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press **View** on the row to open **Preliminary information**. At the top you see the sales status, the next callback and any transfer notice. You can update:',
        },
        {
          type: 'list',
          items: [
            '**Product lines**: they decide the selectable products and the request-specific fields.',
            '**Offer rows**: products, quantities, prices and VAT.',
            '**Status**: some statuses require a **Note**.',
            '**Client details**, **Attribution** and **Team** (**Supervisor** and **Account managers**).',
            'The **Additional information**, which depends on the categories picked.',
          ],
        },
        {
          type: 'paragraph',
          text: 'At the bottom you find the **Notes**, **Documents** and **History** tabs. Press **Save**: you go back to the table, on the category tab you started from (for example **GOL Abruzzo**). The same goes for **Transfer contact**, next to **Save**: once the transfer is done you go back to the table, because the contact moves to the new site and operator.',
        },
        {
          type: 'warning',
          text: 'Closing with a positive outcome requires the client\'s tax code or VAT number.',
        },
        {
          type: 'paragraph',
          text: 'If you open the link of a request that no longer exists you see **Record not found**; if the request is outside your visibility you see **Access denied**. In both cases go back to the table with **Back**.',
        },
      ],
    },
    {
      id: 'row-actions',
      title: 'Row actions',
      blocks: [
        {
          type: 'paragraph',
          text: "From a single row's menu: **Documents**, **Notes**, **Transfer contact**, **Delete**, **History**. Selecting several rows, the **Actions** menu offers:",
        },
        {
          type: 'table',
          headers: ['Action', 'What it does'],
          rows: [
            [
              'Assign operators',
              '**Balanced split** shows the operators grouped by Site (all selected, deselectable per group or individually) and distributes across whoever stays selected; **Assign to operator** assigns everything to the same person.',
            ],
            [
              'Assign Tutor',
              'Assigns the same user to every selected request (the role name may change per category).',
            ],
            ['Transfer contact', 'Moves the requests to another site and another operator.'],
            ['Delete selected', 'Deletes the selected rows.'],
          ],
        },
        {
          type: 'tip',
          text: 'If no operator is enabled on every selected request, **Assign to operator** is unavailable: use **Balanced split**.',
        },
        {
          type: 'note',
          text: "Each request's new **Operator** receives the **You were assigned as Account Manager** notification with the quote's title and details; the link opens the quote, or the request here when they cannot access Quotes. The other Account Managers receive no notification.",
        },
      ],
    },
    {
      id: 'transfer-notifications',
      title: 'Who receives transfer notifications',
      blocks: [
        {
          type: 'paragraph',
          text: 'When a contact is transferred, the notification (bell and email) only goes to:',
        },
        {
          type: 'table',
          headers: ['Who', 'What they receive'],
          rows: [
            ['Whoever had the contact', 'A notice that the contact is no longer theirs, and who it went to.'],
            ['Whoever receives the contact', 'A notice that the contact was assigned to them.'],
            [
              'Whoever holds the **View all** permission',
              'A summary of every transfer in the module, even when not involved.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'Other users receive nothing, even if they work on the same site. Whoever performs the transfer never receives their own notification, and nobody receives it twice.',
        },
      ],
    },
    {
      id: 'statistics-moved',
      title: 'Where the statistics are',
      blocks: [
        {
          type: 'paragraph',
          text: 'Statistics are no longer in the Request Management or Enrollee Management tables: they have their own page, **Opportunities and Work Orders › Request Management Statistics** (see its guide). Enrollee Management no longer has statistics.',
        },
      ],
    },
  ],
}

export default guide
