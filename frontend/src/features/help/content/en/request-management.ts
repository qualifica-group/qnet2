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
          text: 'It lives in **Sales › Request Management**. Each row of the list corresponds to a Quote linked to an Opportunity.',
        },
        {
          type: 'paragraph',
          text: 'At the top there is a tab for every product category, plus **All**: pick one to see only the requests of that category. The tabs that do not fit on the row move into **More (N)**, on the right: open it, type part of the name to filter the list and pick the category with a click or with the arrow keys and **Enter**. The chosen category always stays visible among the tabs.',
        },
        {
          type: 'paragraph',
          text: 'What you see depends on your permissions. With no other permission you see the requests whose **Team** you belong to: as operator, Supervisor or Account Manager. **View by site** adds those of your sites; **View by competence** those with a product in a category of your assignment scope, in any site (with **Scope on all categories**, every one); **View all** every one. If you create a request outside your scope, you will no longer see it unless you have one of these permissions.',
        },
        {
          type: 'paragraph',
          text: 'To keep the categories you use at hand, mark them as favorites: in the menu (**More (N)**, or the star to the right of the tabs when they all fit) tap the star next to the category. Favorites come first among the tabs and at the top of the menu. Turn on **Show favorites only** to keep only them among the tabs (plus the category you are viewing); the others stay in the menu. With **Select all** and **Deselect all**, in the menu, you add every category to your favorites or remove them all at once; the two buttons are hidden while you are searching. Favorites are saved on your user, even on another computer, and are separate for Request Management and Enrollee Management.',
        },
        {
          type: 'paragraph',
          text: "In the **More (N)** menu the **Parent categories** switch limits the tabs to the first-level categories only: the tab of a parent category also gathers the requests of its subcategories (a request counts once) and adds the **Additional information** columns assigned to the parent category (those of a subcategory appear only in its own tab). Unlike a single category, it does not rename the manager fields. The choice is saved in your browser for this list, each mode remembers its own tab and the favorites are separate between the two modes. The switch is on by default: turn it off to see all the categories.",
        },
        {
          type: 'paragraph',
          text: "Until you change your favorites, the initial ones are the categories you are enabled for (based on the assignment scope on your profile) and **Show favorites only** is already on: among the tabs you see **All** and your categories, the others stay in **More (N)**. A notice in the menu reminds you they are automatic suggestions. As soon as you tap a star or the switch, only your own choice applies and it no longer follows your assignment scope. If you are enabled for every category or have no assignment scope, you see all the tabs.",
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
          type: 'paragraph',
          text: 'Every column sorts with a click on its header and filters from the header menu. **Product category** and **Product lines**, which can hold several entries, sort by their alphabetically first entry. **Change requests** filters by number (for example greater than 0 to see only requests with pending proposals) and **Created at**, hidden at first, by date.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['Company name', "The name of a company client, from its registry card. It can be sorted and filtered; it is edited from the panel, not from the cell, and the **Search** box does not look at it."],
            ['VAT number', "The client's VAT number. It can be sorted, filtered and edited from the cell."],
            ['Source', 'The channel the request comes from (a field of the quote).'],
            ['Change requests', 'Change proposals still awaiting a decision.'],
            ['Product lines', 'Products present in the quote; the cell opens the editor with the product catalogue.'],
            ['Account manager 1, 2, 3...', "One column per team position. In **All** they are called **Account manager** with the number and go up to the highest position in use. In a category tab only the positions the category names are shown (for example in Formazione **Tutor**, **Operator**, **Business partner**, **Referrer**, **APL operator**): when the category adds a name, the column appears. Position 2 is the **Operator**, the person working the request. They can be sorted, filtered by name and edited from the cell."],
            ['Account managers / Other account managers', "In **All**, **Account managers** shows everyone on the team, operator included. In a category tab it is called **Other account managers** and shows only the people in a position the category does not name; editing it from the cell changes only these people, those in the named columns stay. It can be filtered by name."],
            ['Operational site', 'The site the request is assigned to.'],
            ['Next callback', 'Date of the next call to the client.'],
            ['Transferred', 'Whether the contact was transferred from another site.'],
            ['Working status', "The quote's working status."],
            ['Estimated value, Success probability, Expected close date', "The opportunity's sales forecast. Hidden at first: turn them on from the column chooser. They can be sorted and filtered, not edited from the cell."],
          ],
        },
        {
          type: 'paragraph',
          text: 'Click the **Product category** cell to change it without opening the request: pick the parent category, then the product category (with **Filter** you can first narrow the list to an intermediate group, for example Consulting › ISO). If the current category is managed as a single row (for example Formazione), the one you pick replaces it; otherwise it is added to the others, and single-row categories cannot be picked (the same holds for the second row of the form). The **X** removes a category. Offer rows whose product belongs to a removed category are deleted on save: a warning lists them first.',
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
            'If you know them, fill in the **Sales forecast**: **Expected close date**, **Estimated value** and **Success probability** (the slider at 0 means "not set").',
            'If needed, set the **Next callback** (the time is optional).',
            'In **Product lines** add at least one row: parent category, then product category. When the parent category has groups (for example Consulting › ISO), **Filter** lets you pick the group and the product category then lists only its subcategories; the filter is not saved, only the product category.',
            "In the **Product catalogue** tick the products and press **Add N selected**, then check quantity, unit price and VAT of the rows (see **Offer rows and product catalogue**).",
            'In **Client details** pick an **Existing registry** or enter a new client.',
            'In **Attribution** pick the **Source** (required) and, if needed, **Reporter**, **Sales rep** and **Operational site**. The Sales rep picked here is set on both the request and its opportunity.',
            'Fill in the **Additional information**, if present.',
            'Press **Create request**: you go back to the table, on the category tab you had open.',
          ],
        },
        {
          type: 'tip',
          text: 'Picking an existing registry hides the identity, contacts and address fields and links the request to that client.',
        },
        {
          type: 'tip',
          text: 'If the new client you are entering already exists, **Possible duplicate** appears under the registry picker: pick that registry instead of creating another one. The **Use** button next to the duplicate picks it at once; if the phone or email belongs to a referent, the button picks the company the referent is linked to. A tax code, VAT number or phone already on another card blocks saving (see the **Registries** guide).',
        },
        {
          type: 'warning',
          text: 'The **Assigned rewards** can only be added after picking a **Reporter**.',
        },
      ],
    },
    {
      id: 'offer-rows-editor',
      title: 'Offer rows and product catalogue',
      blocks: [
        {
          type: 'paragraph',
          text: 'The same editor is found in the **Product lines** cell of the table (it opens in a dialog), in the request card and in the **New request** form. On the left is the **Product catalogue**, on the right the **Offer rows**; on narrow screens they stack one under the other.',
        },
        {
          type: 'steps',
          items: [
            'In the **Product catalogue** search by name or description, or filter with the chips: **All** and one chip for each product category picked in the request.',
            'Each product shows name, code, type (colored badge), price with unit of measure, parent category › category path and description.',
            'Tick one or more products with the checkboxes and press **Add N selected**: one row is created per product, with quantity 1 and the product\'s price and VAT.',
            'On each row edit quantity, unit price and VAT: you see taxable amount, VAT and total. The remove button deletes the row. At the bottom is the **Offer total**.',
          ],
        },
        {
          type: 'list',
          items: [
            "If the product has suppliers, the row shows **Supplier** with the product suppliers: the default is already chosen and the field is required. The row price is the chosen supplier's price and changes if you change supplier. If the supplier has a Supplier commission on that product, a notice appears next to the supplier (for example '10% commission paid'). Products proposed automatically arrive with the default supplier.",
            'A product already in the rows shows **Already added** and cannot be selected: change the quantity on the row.',
            'Single-row categories (for example Formazione) have no checkboxes: clicking a product replaces the one in the single row.',
            'With no product category picked the catalogue is disabled: pick the category first.',
            'In simplified categories the row shows only the amounts: quantity, price and VAT are filled in by the system.',
            'If the category has a single product, the row is created automatically.',
            'A row **assigned to a work order** shows "Assigned to COM-..." and is locked: it cannot be changed or removed until the work order is deleted. If you change the category of a request that has a locked row, the save is refused.',
          ],
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
            '**Product lines**: they decide the selectable products and the request-specific fields. Changing or removing a line empties the offer rows whose product belongs to a category no longer picked: pick the product again.',
            '**Offer rows**: products picked from the **Product catalogue**, with quantities, prices and VAT.',
            "**Sales forecast**: expected close date, estimated value and success probability. They are the opportunity's values: changing them here changes them in **Opportunities** and in the other requests of the same opportunity too.",
            '**Status**: some statuses require a **Note**. A validated status requires at least one offer row. Closing does not, with either a positive or a negative outcome: the product row becomes mandatory when the contract is validated.',
            "**Client details**: the field at the top shows the request's client and lets you pick another one. The change applies to every offer on the opportunity. After picking another client, its details appear once you save, and any edits to the previous client's details are not saved. If a contract already exists, or the client comes from the lead, the field cannot be changed.",
            '**Attribution** (**Source**, **Reporter**, **Sales rep**) and **Team** (**Supervisor** and **Account managers**). A **Sales rep** changed here applies to this request (the quote) only: the opportunity\'s one does not change. Whoever you add among the **Account managers**, in any position (also from the table\'s **Account manager** columns), receives the **You were assigned as Account Manager** notification in the bell and by email; people only moved to another position and people adding themselves do not.',
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
          type: 'warning',
          text: 'When you edit the client details, from the panel or directly in the table cell, you cannot enter a tax code, VAT number or phone that already belongs to another card: the save is refused. You can instead enter the phone of a referent linked to that client. The values already on the client stay editable.',
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
          text: 'Other users receive nothing, even if they work on the same site. Whoever performs the transfer never receives their own notification, and nobody receives it twice. The link opens the quote, or the request here when they cannot access Quotes; with neither access the notification arrives without a link.',
        },
      ],
    },
    {
      id: 'statistics-moved',
      title: 'Where the statistics are',
      blocks: [
        {
          type: 'paragraph',
          text: 'Statistics are no longer in the Request Management or Enrollee Management tables: they have their own page, **Statistics › Request Management Statistics** (see its guide). Enrollee Management no longer has statistics.',
        },
      ],
    },
  ],
}

export default guide
