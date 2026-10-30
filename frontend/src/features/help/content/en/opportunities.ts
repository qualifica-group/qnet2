import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'opportunities',
  title: 'Opportunities',
  summary: 'An opportunity is a commercial deal with a registry, born from a lead or created by hand.',
  sections: [
    {
      id: 'create-an-opportunity',
      title: 'Creating an opportunity',
      blocks: [
        {
          type: 'paragraph',
          text: 'An opportunity is a deal with a **Registry**. It is born from a lead or created by hand.',
        },
        {
          type: 'steps',
          items: [
            'Open **Sales › Opportunities**.',
            'Click **New opportunity**.',
            'Fill in the sections (see table): the form looks like the detail, with its rows **closed**. Open a row with the pencil, fill the field and press **Done** to keep it (or **Revert** to put it back as it was).',
            'Click **Save** (top or bottom): it checks every field and creates the opportunity; errors show under the rows to fix. Leaving without saving asks for confirmation.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields'],
          rows: [
            ['Originating lead', '**Lead** (optional, prefills and locks the derived fields; visible only to the roles allowed to, normally the super-admin)'],
            ['General notes', 'Free-form text'],
            ['Details', '**Title** (optional), **Start date**, **Expected close date**, **Estimated value**, **Success probability (%)**'],
            ['Registry and contacts', '**Registry** (required), **Contact**, **Sales rep**, **Reporter** with the **Assigned rewards**'],
            ['Classification', '**Product lines** (at least one row: parent category, optional intermediate **Filter**, product category), **Products of interest**'],
            ['Team', '**Supervisor**, **Account managers**'],
          ],
        },
        {
          type: 'tip',
          text: 'The **Title** is suggested automatically: the opportunity code followed by the products on the revenue lines of its quotes, not the products of interest (for example OPP_12 - ISO 9001 + SOA); with no products yet it is just the code. On the detail the field shows the current title: a title you type stays yours, even when the quotes change; clear the field to go back to the automatic title.',
        },
        {
          type: 'tip',
          text: 'When you pick the **Registry**, **Sales rep**, **Reporter**, **Supervisor** and **Account managers** are filled with the registry\'s own. If you already entered different ones, QNet asks whether to **Replace** your values or **Keep mine**: nothing you chose is cleared without confirmation.',
        },
        {
          type: 'paragraph',
          text: "Whoever you add as **Supervisor** or among the **Account managers** receives the **You were assigned as Supervisor** or **You were assigned as Account Manager** notification (bell and email). The link opens the opportunity; whoever cannot access Opportunities receives it without a link. Whoever is also an Account manager of one of the opportunity's quotes, for example because they are copied onto the linked quote of a converted lead or of a category with synchronized managers, receives the quote notification only.",
        },
      ],
    },
    {
      id: 'editing-an-opportunity',
      title: 'Editing an opportunity',
      blocks: [
        {
          type: 'paragraph',
          text: "Opportunity, Quote, Contract and Work order records show the same sections in the same place. Here you find **Opportunity data** and **Context** (Product lines, Products of interest), then **Registry and contacts** and **Rewards**, then **Team**. The Rewards section stays visible even when empty (\"No rewards\").",
        },
        {
          type: 'paragraph',
          text: 'There is no separate edit page: the opportunity is edited **directly from its detail**, one field at a time.',
        },
        {
          type: 'steps',
          items: [
            'Open the opportunity from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text and date fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it as it was, without saving.',
          ],
        },
        {
          type: 'tip',
          text: "The **General notes** sit in the yellow box at the top of the detail, as in Request management: when empty the box invites you to write them; with the pencil (or a click on the text) you write them right inside the box.",
        },
        {
          type: 'note',
          text: "Some fields carry other values into the same save: changing the **Registry** gives the Contact the new registry's main referent (it stays empty if the registry has none; the same happens when you pick a **Lead**, if the registry changes or the Contact is empty) and proposes the registry's Sales rep, Reporter, Supervisor and Account managers (asking first if they differ); changing the **Classification rows** removes the Products of interest they no longer cover; the **Assigned rewards** are edited from the **Reporter** field, whose reward they are.",
        },
        {
          type: 'note',
          text: 'A field with no pencil is not editable by you: your role makes it read-only, or it is inherited and locked by the originating lead (Registry), or changing it would change a field you cannot touch. The **Originating lead** cannot be changed and, when your role cannot see it, it shows neither on create nor in the detail; the **Status** is computed from the quotes.',
        },
      ],
    },
    {
      id: 'category-tabs',
      title: "Filtering by product category",
      blocks: [
        {
          type: 'paragraph',
          text: "Above the list is the category bar: the **All** tab and one tab for each product category present, with the number of opportunities. Clicking a tab shows only the opportunities of that category. Your choice is kept after reloading the page.",
        },
        {
          type: 'paragraph',
          text: "An opportunity appears under the tab of every category among its product lines (it counts once even with several lines of the same category). The number next to the name is how many opportunities, among those you can see, fall under that category.",
        },
        {
          type: 'steps',
          items: ["If the tabs do not fit the row, the others move into the **More** menu, where you can search a category by name.","Click the **star** next to a category (in the menu) to add it to your **favorites**: favorites come first.","Turn on **Show only favorites** to see only the favorites in the bar; the others stay in the **More** menu."],
        },
        {
          type: 'note',
          text: "Favorites are separate for each list (Opportunities, Quotes, Request Management). The first time they are suggested from the categories you are enabled for; as soon as you use the star they become yours. The statistics panel and the export do not follow the tab: they always cover all the opportunities.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Quick editing from the list",
      blocks: [
        {
          type: 'paragraph',
          text: "Clicking an editable cell (Name, Registry, Contact, Sales rep, Supervisor, Account managers, Product category, Products of interest, Estimated value, Probability, Start date, Expected close date) edits it **directly in the list**, with the **same rules as the detail**: an invalid value or a field you cannot change is refused with a message and the cell goes back to its previous value.",
        },
        {
          type: 'note',
          text: "Changing the **Registry** from the list gives the Contact the new registry's main referent (empty if it has none; if you cannot edit the Contact and the row already has one, the registry change is not allowed) and fills the **empty** roles (Sales rep, Reporter, Supervisor, Account managers) with the new registry's ones; roles already set stay as they were (the list does not ask for confirmation). The Contact is picked among the row's registry contacts; an empty Name goes back to the automatic title.",
        },
        {
          type: 'paragraph',
          text: "Removing or adding people in **Account managers** keeps everyone else in their position; newcomers take the first free slot. Operational site, Status (computed from the quotes), Business function (derived from the category) and Created at stay read-only.",
        },
      ],
    },
    {
      id: 'from-a-lead',
      title: 'Inheritance from a lead',
      blocks: [
        {
          type: 'paragraph',
          text: 'If the opportunity is born from a lead, some fields are inherited and locked: a banner at the top signals it. **Account managers** are synced with the linked quote. The opportunity no longer has a **Source**: it is a field of the quote and, when you create a quote from an opportunity born from a lead, it is prefilled with the lead\'s Source.',
        },
        {
          type: 'warning',
          text: 'A registry can have only one open opportunity at a time. If one already exists, QNet proposes **Add the offer to this opportunity**.',
        },
      ],
    },
    {
      id: 'status-and-constraints',
      title: 'Status and constraints',
      blocks: [
        {
          type: 'paragraph',
          text: 'The opportunity\'s **Status** is not set by hand: it is computed from the statuses of its quotes (for example "2 statuses" if they differ). On the record you find the list of **Quotes** and the **New quote** button.',
        },
        {
          type: 'warning',
          text: 'Some product categories allow only one quote per opportunity.',
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which opportunities you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; then:" },
        { type: 'list', items: ["With **View** you only see opportunities where you are the **Supervisor** or one of the **Account managers**.", "With **View all** you see all of them."] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to an opportunity you cannot see shows an access error. Seeing an opportunity does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
        { type: 'note', text: "If you create an opportunity without being in its team, you will no longer see it unless you have **View all**. The **Quotes** panel inside an opportunity only lists the quotes you can see." },
      ],
    },
  ],
}

export default guide
