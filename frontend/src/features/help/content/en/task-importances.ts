import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-importances',
  title: 'Task Importances',
  summary: "A Task Importance indicates a task's relevance.",
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: "It is configured in **Configuration › Task Importances** and appears in the task's **Importance** field.",
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
            ['Default', 'When on, prefills the Importance field on new tasks when none is chosen.'],
          ],
        },
        {
          type: 'note',
          text: 'Only one importance level at a time can be the default: turning it on for one automatically turns it off for every other. A deactivated level cannot be the default.',
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
            'Press **New task importance**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'To deactivate, press the pencil on **Active** and turn it off: the tasks using it stay intact.',
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
