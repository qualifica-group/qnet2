import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-priorities',
  title: 'Task Priorities',
  summary: "A Task Priority indicates a task's urgency.",
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: "It is configured in **Configuration › Task Priorities** and appears in the task's **Priority** field.",
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters.'],
            ['Description', 'Optional, at most 500 characters.'],
            ['Color', 'Required.'],
            ['Icon', 'Optional.'],
            ['Active', 'When off, the entry disappears from the dropdowns.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, deactivate',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New task priority**.',
            'Fill in the fields and press **Save**.',
            'To edit, choose **Edit** on the row, change the data and press **Save**.',
            'To deactivate, open **Edit** and turn off **Active**: the tasks using it stay intact.',
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Change the order',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Reorder** in the top bar.',
            'Drag a row from the **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away and applies to the table and every dropdown.',
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
          text: 'An entry used by a task cannot be deleted: deactivate it instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
