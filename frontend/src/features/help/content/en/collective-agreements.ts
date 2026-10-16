import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'collective-agreements',
  title: 'Collective agreements',
  summary: 'Collective agreements and their levels are picked in a person’s employment condition.',
  sections: [
    {
      id: 'overview',
      title: 'What collective agreements are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A collective agreement (CCNL, the national collective labour contract) groups the classification **levels**. In the user record, a person’s employment condition names the level applied; the agreement is the one the level belongs to.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'The name of the agreement, unique.'],
            ['Code', 'Optional code of the agreement.'],
            ['Levels', 'Ordered list of levels: each has a required code (unique within the agreement) and an optional description.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, delete',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New collective agreement** and fill in name and code.',
            'In the **Levels** row use **Add level**, fill in code and description; the arrows move a level up or down, the bin removes it. Then press **Save**.',
            'To edit, open the panel with **View**, press the pencil on the row and save: only that row is saved. The levels list is saved as a whole, in the order shown.',
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A level used by an employment condition cannot be removed, and an agreement with a level in use cannot be deleted: change the level in the affected conditions first.',
        },
      ],
    },
  ],
}

export default guide
