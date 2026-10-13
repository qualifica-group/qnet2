import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-statuses',
  title: 'Task Statuses',
  summary: "Task Statuses describe a task's progress: the phase it is in and its completion percentage.",
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: 'They are configured in **Configuration › Task Statuses**. For every status you set Name, Description, Color, Icon and the **Phase** it belongs to: Open, Pending, To validate, Closed (positive outcome) or Closed (negative outcome).',
        },
        {
          type: 'paragraph',
          text: 'Every status also has a **Completion percentage**: the value the task shows once that status is set.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create and edit',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New status**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
          ],
        },
      ],
    },
    {
      id: 'system-statuses',
      title: 'System statuses and deactivation',
      blocks: [
        {
          type: 'paragraph',
          text: 'The three system statuses (one pinned first, two pinned last) only allow changing the name, color, icon and completion percentage: every other field stays fixed and its row shows no pencil.',
        },
        {
          type: 'note',
          text: 'A deactivated entry disappears from the dropdowns, but the tasks that use it stay intact.',
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
            'Open **Task Statuses** and press **Reorder** in the top bar.',
            'Drag a row from the **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away.',
          ],
        },
        {
          type: 'note',
          text: 'The system statuses stay pinned at the top or at the bottom: only custom statuses move freely.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A status in use cannot be deleted: deactivate it instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
