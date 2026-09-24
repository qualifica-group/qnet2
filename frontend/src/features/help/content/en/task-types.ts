import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-types',
  title: 'Task Types',
  summary: "Task Types classify the kind of activity a task is, for example a call or a site visit.",
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: "They are configured in **Configuration › Task Types** and appear in the task's **Type** field.",
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
            ['Default', 'When on, prefills the Type field on new tasks when none is chosen.'],
          ],
        },
        {
          type: 'note',
          text: 'Only one type at a time can be the default: turning it on for one automatically turns it off for every other. A deactivated type cannot be the default.',
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
            'Press **New task type**.',
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
