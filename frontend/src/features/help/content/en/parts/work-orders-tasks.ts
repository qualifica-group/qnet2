import type { HelpSection } from '../../../types'

/** Task-tab sections of the work-orders guide, split out to stay under the file-size limit. */
export const taskSections: HelpSection[] = [
    {
      id: 'task-board-selection',
      title: 'Tasks: multiple selection',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the detail\'s **Task** tab every main task has a selection checkbox: selecting one or more shows the bulk action bar at the bottom (Assign, Complete, Reopen, Block, Priority, Dates). Sub-tasks cannot be selected.',
        },
        {
          type: 'list',
          items: [
            '**Select all**, in the filter bar, selects every visible task of every phase; clicking it again clears them.',
            'The checkbox next to each **phase** name (**No phase** included, and at the top of each column in the Board view) selects or clears every task of that phase.',
            'When only some of the tasks are selected the checkbox shows a dash: one click completes the selection.',
          ],
        },
        {
          type: 'note',
          text: '"Select all" checkboxes only cover the tasks shown under the current filters. On a closed work order the board is read-only and the checkboxes do not appear.',
        },
      ],
    },
    {
      id: 'task-template-reload',
      title: 'Tasks: loading a template',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the **Tasks** tab, from the **...** menu at the top right (on a work order with no tasks yet also from the button in the middle of the tab), **Load task template** applies a **Task template** to an existing work order: pick the template and press **Load template**. It works both for a work order created without a template and to reload the template (the same one, if it changed in the meantime, or another).',
        },
        {
          type: 'list',
          items: [
            'The tasks created from the current template, with all their sub-tasks (manually added ones too), are **deleted** and replaced by those of the chosen template, assigned to the Supervisors as at creation. Manually created tasks stay.',
            'If even one of those tasks is **completed** the template is not reloaded and nothing changes: the message says how many.',
            'Phases left empty are deleted; those with manually created tasks or logged hours stay. A phase of the new template with the same name as an existing one reuses it, the others are added at the end.',
            'Hours already logged on the deleted tasks stay on the work order.',
          ],
        },
        {
          type: 'note',
          text: 'You need the permission to edit the work order. On a closed work order the board is read-only and the item is not shown. The **Task template** field in the Info tab shows the template loaded last.',
        },
      ],
    },
]
