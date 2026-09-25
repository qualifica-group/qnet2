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
          text: 'At the top there is a tab for every product category, plus **All**: pick one to see only the requests of that category.',
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
            'Press **Create request**.',
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
          text: 'Press **Save**. At the bottom you find the **Notes**, **Documents** and **History** tabs.',
        },
        {
          type: 'warning',
          text: 'Closing with a positive outcome requires the client\'s tax code or VAT number.',
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
      ],
    },
    {
      id: 'statistics-access',
      title: 'Who can use the statistics',
      blocks: [
        {
          type: 'paragraph',
          text: 'The statistics show how work on requests progressed over a chosen period, on screen or in a CSV/Excel file. The panel and the file use the same filters and the same calculations, so the numbers match.',
        },
        {
          type: 'paragraph',
          text: '**GA2** is the level-2 account manager, i.e. the operator working the request. In the statistics, every "per operator" value refers to the request\'s current GA2, not to whoever actually performed the action.',
        },
        {
          type: 'list',
          items: [
            "The module's **Generate report** permission is required; without it, the statistics button does not appear. Request Management and Enrollee Management each have their own permission.",
            'Everyone sees only the requests they already see in the list: with **View all**, all of them; otherwise the ones where they are GA2, plus the ones of their own sites with **View by site**.',
            'The filters can only narrow this visibility, never widen it.',
          ],
        },
      ],
    },
    {
      id: 'statistics-panel',
      title: 'Open and read the statistics panel',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Opportunities and Work Orders › Request Management**.',
            'At the top right, next to **New request**, click the chart icon (**Show statistics**).',
            'The panel opens above the category tabs. Click the icon again to close it (**Hide statistics**).',
          ],
        },
        {
          type: 'note',
          text: 'The browser remembers whether the panel was open and the last filters applied.',
        },
        {
          type: 'table',
          headers: ['Part', 'Content'],
          rows: [
            [
              'Applied filters',
              'A box for Period, Categories, Sites, Operators, Rows: colored if it narrows the data, neutral if it means "everything". On the right, **Generate report** and **Filters**.',
            ],
            [
              'Overall total',
              'One box per column, across every selected category together. A request present in several categories counts once.',
            ],
            [
              'Summary per category',
              'One box for every column configured for the category, with the total value (zeros included).',
            ],
            [
              'Charts per category',
              '**Indicators** compares the totals of the columns; then a chart per column with a bar per operator, from the highest value down.',
            ],
          ],
        },
        {
          type: 'paragraph',
          text: 'Every section collapses and expands with the arrow next to its title. The double-arrow button next to **Filters** opens everything at once (**Expand all**, charts included) or, when everything is already open, collapses every section (**Collapse all**).',
        },
        {
          type: 'table',
          headers: ['Panel', 'CSV/Excel file'],
          rows: [
            ['Updates right away', 'Is prepared and then downloaded'],
            ['Has the Overall total', 'Has no overall total row'],
            ['Per-operator values only in the charts', 'One row for every operator'],
            [
              "Only each category's configured columns",
              'Every column used by at least one selected category',
            ],
          ],
        },
      ],
    },
    {
      id: 'statistics-filters',
      title: 'The statistics filters',
      blocks: [
        {
          type: 'paragraph',
          text: 'Click **Filters** to open the **Report and statistics filters** panel: change the values and press **Apply** (or **Cancel**). **Reset filters** restores the initial values (today, every category, site and operator, Everything mode): the change takes effect only after **Apply**. The same filters apply to charts and file.',
        },
        {
          type: 'table',
          headers: ['Filter', 'What it does', 'Default value'],
          rows: [
            ['From / To', 'The period considered, full days included. Either field, or both, can be left empty: only To = everything up to that date; only From = everything from that date on; both empty = no date limit.', 'Today (From and To)'],
            ['Categories', 'Which categories to include.', 'All'],
            ['Sites', 'Limits to the operators of those sites.', 'All'],
            ['Operators', 'Limits to certain GA2 operators.', 'All'],
            ['Rows to include', 'Total only, Operators only or Everything.', 'Everything'],
          ],
        },
        {
          type: 'note',
          text: 'When both are filled in, To cannot be earlier than From. The date used changes column by column (see the columns table). Unhandled Callbacks, Unhandled New Contacts and Potential Leads ignore the period and look at the situation today; their "(selected period)" versions use it.',
        },
        {
          type: 'list',
          items: [
            'First click on a category with subcategories: it selects only the category.',
            'Second click: it also adds every subcategory.',
            'Third click: it removes the category and its subcategories.',
          ],
        },
        {
          type: 'note',
          text: "Even selecting only the parent category, its row already counts the requests of the subcategories. At least one category is required; **Select all** selects or clears the whole list.",
        },
        {
          type: 'paragraph',
          text: "Sites and Operators only appear with Operators only or Everything. A request's site, here, is its GA2's site, not the request's own operational site; filtering by site leaves the requests without an operator excluded.",
        },
        {
          type: 'warning',
          text: 'With Total only the Sites and Operators filters do not apply: the total includes everyone. The choices stay saved for when you switch mode.',
        },
      ],
    },
    {
      id: 'statistics-report-structure',
      title: 'Report structure',
      blocks: [
        {
          type: 'paragraph',
          text: 'Which categories appear is decided in **Products › Product Categories**: **Visible in reports** puts the category in the report (subcategories inherit it and can force it to no, excluding their own too); **Report columns** picks the calculated columns. An excluded category is not counted even in the totals of the parent category.',
        },
        {
          type: 'paragraph',
          text: 'A request belongs to every category of at least one of its products, subcategories included: a request with products from two categories counts in both.',
        },
        {
          type: 'list',
          items: [
            'The rows of the file: categories appear in alphabetical order, each followed by its selected subcategories (also alphabetical).',
            'For each category: a TOTAL row, then operators in alphabetical order, and finally Unassigned.',
          ],
        },
        {
          type: 'note',
          text: 'An operator only appears if it has at least one value different from zero; Unassigned only if there are requests with no operator contributing to the counts.',
        },
        {
          type: 'paragraph',
          text: 'The columns of the file: first the fixed Category and GA2 (TOTAL, operator name or Unassigned), then the statistics columns used by at least one selected category. If a column is not configured for the row\'s category, the cell stays empty; a 0 means a configured column with no results.',
        },
      ],
    },
    {
      id: 'statistics-columns',
      title: 'The statistics columns',
      blocks: [
        {
          type: 'table',
          headers: ['Column', 'What it counts', 'Period and notes'],
          rows: [
            [
              'Calls Made',
              "The notes linked to the request and written by its GA2: every note counts as one call.",
              'Note creation date. Excludes requests still Open, deleted notes, general notes and notes written by others; always 0 for Unassigned.',
            ],
            [
              'Unhandled Callbacks',
              "Requests with a callback date of today or earlier, not yet closed.",
              "Ignores the period: it always looks at today's date.",
            ],
            [
              'Unhandled Callbacks (selected period)',
              'Requests not yet closed with a callback date within the period.',
              'Callback date; future callbacks count too if they fall in the period.',
            ],
            [
              'Unhandled New Contacts',
              'Requests still in the Open status.',
              'Ignores the period: requests created earlier count too.',
            ],
            [
              'Unhandled New Contacts (selected period)',
              'Requests created in the period and still in the Open status.',
              'Request creation date.',
            ],
            [
              'Potential Leads',
              'Requests currently in a status of the Pending or Validated group.',
              'Ignores the period: it looks at the current status.',
            ],
            [
              'Potential Leads (selected period)',
              'Requests moved in the period to a status of the Pending or Validated group.',
              'Status change date; each request counts once.',
            ],
            [
              'Enrolled',
              'Requests moved in the period to a status of the Closed (positive outcome) group.',
              'Status change date; it still counts even if the request later moved back.',
            ],
            ['Deals Closed', 'Same calculation as Enrolled.', 'Only the name changes, depending on which categories use it.'],
            ['Handover Sent', 'Same calculation as Enrolled.', 'Only the name changes, depending on which categories use it.'],
            [
              'Companies Added',
              'Company-type registries created in the period and linked as the client of a request.',
              'Registry creation date; in the TOTAL, each company counts once.',
            ],
            ['Classes In Progress', 'Not yet computed.', 'Always 0.'],
            ['Classes Starting', 'Not yet computed.', 'Always 0.'],
            ['Appointments Booked', 'Not yet computed.', 'Always 0.'],
          ],
        },
        {
          type: 'note',
          text: 'In Companies Added the sum of the operator rows can exceed the TOTAL, if the same company is linked to requests of different operators. In the other columns the TOTAL equals the sum of the rows.',
        },
        {
          type: 'tip',
          text: 'Status changes are counted whether made from Request Management or from the Quotes module.',
        },
      ],
    },
    {
      id: 'statistics-export',
      title: 'Generate the file',
      blocks: [
        {
          type: 'steps',
          items: [
            'Check the filters in **Applied filters**.',
            'Click **Generate report** and pick **CSV** or **Excel (XLSX)**.',
            'Wait for the "Generating…" message: stay on the page.',
            'When done, "Report generated: the download started automatically." appears.',
          ],
        },
        {
          type: 'paragraph',
          text: 'No separate notification arrives. The file is named, for example, request-management-report-2026-09-14_2026-09-18.xlsx (the From and To dates). With only one bound it becomes ...-from-DATE or ...-to-DATE; with no dates, just request-management-report.xlsx.',
        },
        {
          type: 'warning',
          text: 'If the preparation fails, "Report generation failed. Please try again." appears.',
        },
      ],
    },
    {
      id: 'statistics-faq',
      title: 'Statistics FAQ',
      blocks: [
        {
          type: 'list',
          items: [
            "**Why do I see 0?** The column is among those not yet computed; nothing happened in the period (check From and To); the notes belong to a user other than the GA2; the request is still Open; the site and operator picked have nothing in common.",
            '**Why is a cell empty instead of 0?** The column is not configured for that category: check Report columns in Product Categories.',
            "**Why doesn't an operator appear?** It has no values different from zero in the period, you picked Total only, it is excluded by the Operators or Sites filters, or you cannot see its requests with your permissions.",
            '**Why is "Unassigned" missing?** There are no requests without an operator contributing to the counts, or you filtered by site.',
            "**Why don't the parent's totals include a subcategory?** The subcategory has Visible in reports set to no, directly or by inheritance.",
            "**Why did an operator's numbers change after a transfer?** The counts look at the current operator: the previous operator's notes no longer count as calls.",
            "**Why don't some columns change with the period?** Unhandled Callbacks, Unhandled New Contacts and Potential Leads always look at the situation today. For the period figure use their \"(selected period)\" version, which you switch on in the category's Report columns.",
            '**Why is the Overall total lower than the sum of the categories?** A request present in several selected categories counts once in the overall total.',
          ],
        },
      ],
    },
  ],
}

export default guide
