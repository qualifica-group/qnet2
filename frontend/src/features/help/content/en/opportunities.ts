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
            'Fill in the sections (see table).',
            'Click **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields'],
          rows: [
            ['Title', '**Title** (optional)'],
            ['Registry and contacts', '**Registry** (required), **Contact**, **Sales rep**'],
            ['Classification', '**Source**, **Operational site**'],
            ['Attribution', '**Reporter**, **Assigned rewards**'],
            ['Business functions and product categories', '**Classification rows**'],
            ['Team', '**Supervisor**, **Account managers**'],
            ['Planning', '**Start date**, **Expected close date**, **Estimated value**, **Success probability (%)**'],
            ['General notes', 'Free-form text'],
          ],
        },
        {
          type: 'tip',
          text: 'The **Title** is suggested automatically: the opportunity code followed by the products on the revenue lines of its quotes, not the products of interest (for example OPP_12 - ISO 9001 + SOA); with no products yet it is just the code. When editing, the field is prefilled: a title you type stays yours, even when the quotes change; clear the field to go back to the automatic title.',
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
