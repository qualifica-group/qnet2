import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'campaigns',
  title: 'Campaigns',
  summary: 'A campaign is the marketing action that generates leads, either standalone or linked to a project.',
  sections: [
    {
      id: 'overview',
      title: 'What a campaign is',
      blocks: [
        {
          type: 'paragraph',
          text: 'A campaign is the marketing action that generates leads. It can be **Standalone** or **Linked to a project**.',
        },
      ],
    },
    {
      id: 'create-a-campaign',
      title: 'Creating a campaign',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Marketing & Leads › Campaigns** and click **New campaign**.',
            'Fill in **Code** and **Name**.',
            'In **Project link**, pick a **Project**, if needed.',
            'Complete the dates, **Total budget** and **Target leads**.',
            'Click **Save**.',
          ],
        },
      ],
    },
    {
      id: 'linking-a-project',
      title: 'Linking a project',
      blocks: [
        {
          type: 'paragraph',
          text: 'Linking a project:',
        },
        {
          type: 'list',
          items: [
            'the **Classification** (status, business function, product category) comes from the project and is read-only;',
            "the project's geographic levels are inherited and locked;",
            '**Partner** and **Site** are prefilled (the site stays editable);',
            "the **Project remaining budget** is shown under the budget.",
          ],
        },
        {
          type: 'tip',
          text: "To change the classification of a linked campaign, unlink the project first.",
        },
      ],
    },
  ],
}

export default guide
