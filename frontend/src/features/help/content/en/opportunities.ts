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
            ['Registry and contacts', '**Registry** (required), **Contact**, **Sales rep**'],
            ['Classification', '**Source**, **Operational site**'],
            ['Attribution', '**Reporter**, **Assigned rewards**'],
            ['Business functions and product categories', '**Classification rows**'],
            ['Team', '**Supervisor**, **Account managers**'],
            ['Planning', '**Start date**, **Expected close date**, **Estimated value**, **Success probability (%)**'],
            ['General notes', 'Free-form text'],
          ],
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
