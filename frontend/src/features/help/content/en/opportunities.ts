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
            'Open **Opportunities & Contracts › Opportunities**.',
            'Click **New opportunity**.',
            'Fill in the sections (see table): the form looks like the detail, with its rows **closed**. Open a row with the pencil, fill the field and press **Done** to keep it (or **Revert** to put it back as it was).',
            'Click **Save** (top or bottom): it checks every field and creates the opportunity; errors show under the rows to fix. Leaving without saving asks for confirmation.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields'],
          rows: [
            ['Originating lead', '**Lead** (optional, prefills and locks the derived fields)'],
            ['General notes', 'Free-form text'],
            ['Details', '**Title** (optional), **Start date**, **Expected close date**, **Estimated value**, **Success probability (%)**'],
            ['Registry and contacts', '**Registry** (required), **Contact**, **Sales rep**, **Reporter** with the **Assigned rewards**'],
            ['Classification', '**Source**, **Business functions and product categories** (at least one row), **Products of interest**'],
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
          text: "Some fields carry other values into the same save: changing the **Registry** clears the Contact and proposes the registry's Sales rep, Reporter, Supervisor and Account managers (asking first if they differ); changing the **Classification rows** removes the Products of interest they no longer cover; the **Assigned rewards** are edited from the **Reporter** field, whose reward they are.",
        },
        {
          type: 'note',
          text: 'A field with no pencil is not editable by you: your role makes it read-only, or it is inherited and locked by the originating lead (Registry, Source), or changing it would change a field you cannot touch. The **Originating lead** cannot be changed; the **Status** is computed from the quotes.',
        },
      ],
    },
    {
      id: 'from-a-lead',
      title: 'Inheritance from a lead',
      blocks: [
        {
          type: 'paragraph',
          text: 'If the opportunity is born from a lead, some fields are inherited and locked: a banner at the top signals it. **Account managers** are synced with the linked quote.',
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
  ],
}

export default guide
