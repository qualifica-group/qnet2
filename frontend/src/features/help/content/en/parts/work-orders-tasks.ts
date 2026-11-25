import type { HelpSection } from '../../../types'

/** Task-tab sections of the work-orders guide, split out to stay under the file-size limit. */
export const taskSections: HelpSection[] = [
    {
      id: 'task-board-roadmap',
      title: 'Tasks: Roadmap view',
      blocks: [
        {
          type: 'paragraph',
          text: "In the **Tasks** tab pick the view with the buttons in the filter bar: **Roadmap**, **List** or **Board**. The **Roadmap** shows the tasks on a timeline: on the left the phases with their tasks, on the right a bar for each task, from the **Start date** to the **End date**. A vertical line marks **today**; on opening the timeline scrolls to today and moves sideways to show the rest.",
        },
        {
          type: 'list',
          items: [
            "At the top right of the Roadmap pick the scale: **Weeks** (one column per week, with the month above), **Months** (one column per month, with the year above) or **Year** (whole years, month by month). The **Today** button brings the timeline back to today's date. The scale is remembered between sessions too.",
            "The bar takes the colour of its phase; a **red** bar is an overdue task, an **orange** one a blocked task, a **grey** one a closed task (with its title struck through). Hovering the bar shows title, dates and state.",
            "A task with a single date shows as a **diamond** on that day; a task with no date stays in its phase marked **No dates**.",
            "Next to each phase name a thin bar shows the period its tasks cover. Sub-tasks sit indented under their parent task.",
            "A click on the bar or on the title opens the task: dates are changed there, or with the **Dates** bulk action. The Roadmap does not move bars by dragging.",
            "Filters, search, the tiles at the top and multiple selection work as in the other views.",
          ],
        },
        {
          type: 'note',
          text: "**Project Work Orders** open the Tasks tab on the **Roadmap**, **Work Orders** on the **List**. The view you pick is remembered for you between sessions, separately for the two work order types.",
        },
      ],
    },
    {
      id: 'task-board-rows',
      title: 'Tasks: rows and quick editing',
      blocks: [
        {
          type: 'paragraph',
          text: "In the **List** view of the **Tasks** tab each task sits on **one row**, like in the Tasks table: above each phase's rows is the column header (Title, Status, Type, Priority, Start date, End date, Requester, Assignees, Completion, Hours worked / estimated). On narrow screens the row wraps and each value has its own label; when the columns do not fit, the phase scrolls sideways. The **Completion** column shows the percentage of the task's status; next to the phase name only the hours remain, no longer the completion. The tiles at the top no longer show completion either: Total tasks, Overdue, Due today, Estimated and Actual remain. The work order's progress stays in the detail header.",
        },
        {
          type: 'list',
          items: [
            "**Check**: the circle before the title completes the task and opens the usual **Complete** pop-up. A completed task has a filled green circle; if you may reopen it, a click on the green circle **reopens** it.",
            "**Edit in place**: click a cell (Status, Type, Priority, dates, Requester, Assignees, Hours) to change its value. Picks from a list are saved at once; dates, estimated time (in minutes) and assignees are saved with **Enter** or by clicking outside. **Esc** cancels.",
            "The **title** opens the task; to rename it use the **pencil** that appears next to it on hover.",
            "Picking a positively closed status opens the Complete pop-up; moving out of a closed status reopens the task, as in the Tasks table.",
            "Rows are **dragged** by the handle on the left to change order or phase; a click on the rest of the row opens the task.",
          ],
        },
        {
          type: 'note',
          text: "A cell you may not edit (because of your role permissions or because the task is write-locked) does not open. The short task description shows when you hover the title. On opening, the filters show **every** task, closed ones included, with any end date; filters left that way do not appear among the **Applied filters**, which show only the ones narrowing the list.",
        },
      ],
    },
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
          text: 'You need the permission to edit the work order, or to be one of its **Supervisors** or **Participants**. On a closed work order the board is read-only and the item is not shown. The **Task template** field in the Info tab shows the template loaded last.',
        },
      ],
    },
]
