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
          text: "Categories whose **Used in** setting (in the category record) is **Opportunities / Quotes** are not part of Request Management: they have no tab, cannot be picked (neither in the form nor in the **Product category** cell) and do not show in filters and statistics. A request that has **only** such categories is not listed, counted or found by search, and its link answers **Access denied**; if it also has a category used here it stays visible. Setting **All modules** back on the category restores everything: no data is deleted.",
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
            ['Account managers', "The only managers column: it shows everyone on the request's team (operator, tutor and every other manager), the same in **All** and in every category tab. It can be filtered by name, **(Blanks)** included for requests with no manager, and it does not sort. From the cell you add or remove people: whoever stays keeps their role, whoever you add takes the first free place and is notified. To choose each person's exact role (for example **Operator** or **Tutor**) open the request with **View** (**Team** section)."],
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
            "In **Product lines** add one row per parent category: pick the parent category, then one or more of its product categories. In the list, **Select all** takes them all in one click and a click on a group takes all its subcategories: the picked categories stay in the same row (for example ISO › ISO 9001, ISO 14001), not one row each. A parent category managed as a single row (for example Formazione) accepts one category only; a parent category already used in another row cannot be picked again. When the parent category has groups (for example Consulting › ISO), **Filter** narrows the list to a group; the filter is not saved, only the categories.",
            "The **Offer** section opens once the first product line is picked. In the **Product catalogue** tick the products and press **Add N selected**, then check quantity, unit price and VAT of the rows; in simplified categories there is only the **Product** field to pick (see **Offer rows and product catalogue**).",
            'In **Client details** pick an **Existing registry** or enter a new client. The new-client card starts as **Individual** or **Company** according to the **Registry in Gestione Richieste** rule of the category picked in the first **Product lines** row; until you type something it follows the category, then you can always change the type by hand.',
            'In **Attribution** pick the **Source** (required) and, if needed, **Reporter**, **Sales rep**, **Commercial partner** and **Operational site**. The Sales rep and the Commercial partner picked here are set on both the request and its opportunity. When you are entering a new client, Source, Reporter, Sales rep and Commercial partner are saved on its registry too.',
            'Fill in the **Additional information**, if present.',
            'Press **Create request**: you go back to the table, on the category tab you had open.',
          ],
        },
        {
          type: 'note',
          text: 'When the **Product lines** belong to several parent categories (for example one ISO row and one APL row), **Create request** creates one request per parent category, all on the same opportunity (before saving, a notice under the **Product lines** says how many requests will be created and for which parent categories): each shows only in its own category tab and carries only its category\'s offer rows and **Additional information**, while the **Rewards** go to the first one only. Rows of several categories under the same parent (for example ISO 9001 and ISO 14001) stay in a single request. A product of a parent category not picked in the lines cannot be added.',
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
        {
          type: 'paragraph',
          text: "At the bottom of the form, in the **Documents** section, attach the request's files: they are uploaded when you save. When the **Product lines** create several requests (one per parent category), next to each file choose the request to put it in (for example **ISO request** or **APL request**); if you remove the chosen parent category from the lines, the file goes to the first one. If a file fails to upload the request is still created and a notice names the file to attach from the **Work** panel. The section shows only to users who can view and upload documents.",
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
            "In simplified categories the offer is a single **Product** field: pick the product from the list (several products when the category allows it; one only in single-row categories) and remove it with the X. Quantity, price and VAT are set by the system (quantity 1, the product's price and VAT); under the field you see the **Indicative value (VAT included)** of the picked products.",
            'If every category of the **Product lines** has the **Hide the offer in Gestione Richieste** rule, the **Offer** section does not appear (in New request and in the Work panel; from the table, editing the rows shows a notice). With mixed categories the catalogue offers only the products of the categories that are not hidden. Rows already saved stay as they are.',
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
            '**Product lines**: they decide the selectable products and the request-specific fields. Changing or removing a line empties the offer rows whose product belongs to a category no longer picked: pick the product again. A request created for a parent category shows only its lines and accepts only categories and products of that parent: for another parent category create a new request.',
            '**Offer rows**: products picked from the **Product catalogue**, with quantities, prices and VAT.',
            "**Sales forecast**: expected close date, estimated value and success probability. They are the opportunity's values: changing them here changes them in **Opportunities** and in the other requests of the same opportunity too.",
            '**Status**: some statuses require a **Note**. A validated status requires at least one offer row. Closing does not, with either a positive or a negative outcome: the product row becomes mandatory when the contract is validated.',
            "**Client details**: the field at the top shows the request's client and lets you pick another one. The change applies to every offer on the opportunity. After picking another client, its details appear once you save, and any edits to the previous client's details are not saved. If a contract already exists, or the client comes from the lead, the field cannot be changed.",
            '**Attribution** (**Source**, **Reporter**, **Sales rep**, **Commercial partner**) and **Team** (**Supervisor** and **Account managers**). A **Sales rep** or **Commercial partner** changed here applies to this request (the quote) only: the opportunity\'s ones do not change. Whoever you add among the **Account managers**, in any position (also from the table\'s **Account manager** columns), receives the **You were assigned as Account Manager** notification in the bell and by email; people only moved to another position and people adding themselves do not.',
            'The **Additional information**, which depends on the categories picked.',
          ],
        },
        {
          type: 'paragraph',
          text: 'At the bottom you find the **Notes**, **Documents**, **Opportunity documents** and **History** tabs. **Documents** are the files of this request (you upload and delete them here); **Opportunity documents** are the ones already on the opportunity, read-only. Press **Save**: you go back to the table, on the category tab you started from (for example **GOL Abruzzo**). The same goes for **Transfer contact**, next to **Save**: once the transfer is done you go back to the table, because the contact moves to the new site and operator. **Transfer to another registry** (same-name permission) moves the offer to another customer instead: choose the new registry, optionally change the offer\'s **Title** and review the **Additional information**; the offer lands on the customer\'s open opportunity or on a new one, as explained in the **Quotes** guide. When a category has **Contact transfer before the contract** (for example **Orientamento specialistico**), the request cannot move to a **Won** status until you have used **Transfer contact**.',
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
          text: "From a single row's menu: **Documents** (that request's files), **Notes** (the top of the window shows the row's **Callback date**, so you see it while writing), **Transfer contact**, **Delete**, **History**. Selecting several rows, the **Actions** menu offers:",
        },
        {
          type: 'table',
          headers: ['Action', 'What it does'],
          rows: [
            [
              'Assign operators',
              '**Balanced split** shows the operators grouped by Site (all selected, deselectable per group or individually) and distributes across whoever stays selected — with **Take the current load into account** on it favours whoever operates fewer offers, off it splits equally; **Assign to operator** assigns everything to the same person.',
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
          text: "With the assignment actions, whoever joins a request's **Account managers** receives the **You were assigned as Account Manager** notification, in the bell and by email, with the quote's title and details, every Account manager included; the link opens the quote, or the request here when they cannot access Quotes. It works the same for every Account manager. It is not sent to whoever was already on the team, whoever is only moved, or whoever assigns themselves.",
        },
      ],
    },
    {
      id: 'quote-under-contract',
      title: 'Offer under contract',
      blocks: [
        {
          type: 'paragraph',
          text: "If the offer has a contract it is **frozen**: the **Work** panel shows the **Offer under contract** notice and, without the **Edit quotes under contract** permission, the fields are read-only (list cells too). With the permission, saving first asks for confirmation in a popup.",
        },
        {
          type: 'note',
          text: "The operational fields stay always editable: **Account managers** (team), **Next callback date**, **Operational site** and **General notes**.",
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
              'Whoever holds the **notifiche-trasferimenti-contatti** role',
              'A summary of every transfer, even when not involved. The **View all** permission shows every request but does not send the summary.',
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
