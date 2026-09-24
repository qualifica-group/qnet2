import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'tasks',
  title: 'Tasks',
  summary: 'Tasks are the activities to carry out, with who asks for them, who does them and a due date.',
  sections: [
    {
      id: 'overview',
      title: 'Who sees a task',
      blocks: [
        {
          type: 'paragraph',
          text: 'They live in **Task › Tasks**. Which tasks you see depends on your role permissions:',
        },
        {
          type: 'table',
          headers: ['Permission', 'What you see'],
          rows: [
            ['None (default)', 'Only your own tasks: the ones you created or requested, or where you are an assignee or a watcher.'],
            ['**View by site**', 'Your tasks plus those where at least one assignee works at one of your sites (physical or remote).'],
            ['**View all**', 'Every task.'],
          ],
        },
        {
          type: 'note',
          text: 'Seeing a task of your site does not let you edit it: for that you need a role on it (creator, requester, assignee) or the **Manage all** permission.',
        },
        {
          type: 'tip',
          text: 'The **Completion** percentage is never entered by hand: it depends on the status chosen, or, when the task has sub-tasks, on the (rounded) average of their own percentages — capped at 99% while the task stays open, so only a truly completed task reaches 100%. The bar and the figure are coloured as in the work order: red up to 33%, amber up to 66%, blue above, green at 100%.',
        },
        {
          type: 'note',
          text: 'Opening a task you have no access to shows an "Access denied" message with the contacts (requester and creator) you can write to: the page has no Retry button, since this is not a temporary error.',
        },
      ],
    },
    {
      id: 'filters-and-statistics',
      title: 'Filters and statistics',
      blocks: [
        {
          type: 'paragraph',
          text: 'The table\'s **advanced filters** narrow the list with the same criteria as the work order\'s Task tab: Status, Due, Assignment, Task status, Type, Priority, Importance, Requester, Assignees and Watchers.',
        },
        {
          type: 'table',
          headers: ['Filter', 'What it shows'],
          rows: [
            ['Status', 'Open (preset), Completed, Blocked, In validation or All.'],
            ['Due', 'Today, Overdue, This week or This month, on the end date (or the start date when missing).'],
            [
              'Assignment',
              'Pick **one or more** values together (preset: Assigned to me): assigned to you, requested by you, assigned by you (you are the requester but not an assignee), created by you (you created it but are neither the requester, nor an assignee, nor a watcher) or observed by you. **All** shows every task where you have any role at all (requester, assignee, watcher or creator), even if you hold the View all permission. If you hold View all or View site you also get **All visible**, which shows every task you may see, including your colleagues\u2019.',
            ],
            ['Account', 'One or more Account records the task is linked to.'],
            ['Work order', 'One or more work orders the task is linked to.'],
          ],
        },
        {
          type: 'tip',
          text: 'The table opens on the tasks **assigned to you and open** only, sorted by the most recently updated first. Pick other values in the Assignment and Status filters to widen the view. When you arrive from a **Dashboard** tile, the table opens already filtered for that visit only, without changing your saved filters.',
        },
        {
          type: 'note',
          text: 'Quick search also matches an exact ID: typing digits only also finds the task with that identifier, on top of the title match.',
        },
        {
          type: 'paragraph',
          text: 'The **Statistics** button opens the Overdue, Due today, Estimated and Actual tiles, with charts by status, by priority and of new tasks per month. Only main tasks (not sub-tasks) you can see are counted, regardless of the table filters.',
        },
      ],
    },
    {
      id: 'creating-a-task',
      title: 'Create a task',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New task**.',
            "Fill in the form's fields (see table).",
            'If you want, add files in **Attachments**: they are uploaded once the task is saved.',
            'Press **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Main fields'],
          rows: [
            ['Task', 'Title (required), Description, Evidence, Parent task.'],
            [
              'Classification',
              "Status (optional on create: when left unpicked, it starts from the default one), Type, Priority and Importance (all three required, prefilled from the catalog's default row), Category (a tree, indented — you can also pick a parent category).",
            ],
            ['Account and contact', "Account, Contact (among the account's own)."],
            [
              'People',
              'Requested by (required), Assignees (at least one), Watchers, Private task, Do not send the opening notification.',
            ],
            ['Scheduling', "Start date, Due date (required, prefilled to today), times, Estimated time (minutes)."],
            [
              'Linked records',
              'Opportunity, Work order or Lead (Opportunity and Work order exclude each other; picking a Work order sets the account); with a Work order, the Phase to place the task in (open phases only, not for subtasks).',
            ],
            ['Closure', 'Feedback required, Validation, Create already completed (create only).'],
            ['Recurrence', 'Frequency and end of the repetition.'],
            [
              'Sub-tasks',
              'Optional rows with a title (required), a due date and assignees: they create child tasks right along with the parent, up to 50 at a time.',
            ],
          ],
        },
        {
          type: 'note',
          text: "A manually picked initial **Status** must be a working one: closing statuses, \"to validate\" ones and statuses reachable only from an action (e.g. Complete) are not selectable on create.",
        },
        {
          type: 'note',
          text: 'A **Private task** is visible only to its creator, requester, assignees and watchers: the View all and View by site permissions do not show it (the super-admin stays the one exception).',
        },
        {
          type: 'note',
          text: 'Turning on **Create already completed** makes the task born already closed with a positive outcome: a time entry with the estimated minutes (even 0) is logged right away, without going through validation. It is not compatible with Feedback required or Validation without the matching feedback.',
        },
        {
          type: 'tip',
          text: 'By default assignees and watchers get the assignment notification on create: turn on **Do not send the opening notification** to create it without alerting them. On edit the same idea is called **Do not notify the newly assigned** and only covers whoever you add with that save.',
        },
        {
          type: 'note',
          text: 'Changing the **Account** clears Contact, Opportunity and Lead, and keeps the Work order only when it belongs to the same account. Opportunity, Work order and Lead only list the records of the chosen account once you picked one.',
        },
      ],
    },
    {
      id: 'people-and-roles',
      title: 'People and roles',
      blocks: [
        {
          type: 'table',
          headers: ['Role', 'Who they are', 'What they can change'],
          rows: [
            ['Created by', 'Who created the task.', 'Everything.'],
            ['Requested by', 'Who asked for the activity.', 'Everything.'],
            [
              'Assignees',
              'Who must carry it out.',
              'Only the description, status, times, completion date and feedback.',
            ],
            ['Watchers', 'Who follows without working on it.', '—'],
          ],
        },
        {
          type: 'note',
          text: 'Every status belongs to a phase: Open, Pending, To validate, Closed (positive outcome), Closed (negative outcome). A task in validation or closed does not accept changes to its main data, except for a super-admin, who can still edit its fields (but not delete or complete it).',
        },
        {
          type: 'note',
          text: "You can delete a task if you can edit it (creator, requester, assignee, or the Manage all permission), as long as it is not completed, in validation, or blocked. Deleting a task cascades to its sub-tasks: if even one of them cannot be deleted, the whole deletion is cancelled.",
        },
        {
          type: 'note',
          text: 'Whoever can edit the task also manages every entry of its **Time tracking**, including ones logged by another assignee.',
        },
      ],
    },
    {
      id: 'task-actions',
      title: 'Task actions and completion',
      blocks: [
        {
          type: 'paragraph',
          text: "The detail only shows the actions available at that moment:",
        },
        {
          type: 'table',
          headers: ['Action', 'What it does'],
          rows: [
            ['Complete', 'Closes the task or sends it to validation.'],
            ['Reopen', 'Moves the task back to "In progress" and lifts a block, if any.'],
            ['Approve', 'Definitively closes a task in validation.'],
            ['Reject', 'Moves a task in validation back to "Assigned", clears the closure feedback, and lifts a block, if any.'],
            ['Block / Unblock', 'Suspends or reactivates the task. You can only block an open task (not completed, not in validation); completing it, reopening it or rejecting its validation unblocks it automatically.'],
            ['Request update', 'Sends an email and a notification to a group of recipients, with a mandatory message (see below).'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Press **Complete**.',
            'If required, write the **Closure feedback**.',
            'If the task requires validation, pick the **Validation status**.',
            'In the **Time tracking** section, log the time spent.',
            'Press **Complete**.',
          ],
        },
        {
          type: 'warning',
          text: "With **Validation** on, an assignee's completion does not close the task: it goes to validation and the requester must approve or reject it. You cannot complete a task with open sub-tasks, nor act on a blocked task.",
        },
        {
          type: 'note',
          text: "Completing a task from the detail (or from the list) logs the time entry for **every assignee**, one identical entry each (yourself alone when the task has none). Completing a single sub-task from the Sub-tasks panel instead only logs it for you — this is not a choice you make, it depends on where you complete the task.",
        },
        {
          type: 'paragraph',
          text: 'The **Request update** action is reserved to the requester, the creator or whoever manages the task (not a plain watcher), and only on a task that is not completed, not in validation and not blocked. Pick one of the three recipient groups:',
        },
        {
          type: 'table',
          headers: ['Recipients', 'Who gets the request'],
          rows: [
            ['Assignees', 'Every assignee; the watchers still receive a copy, marked **CC**.'],
            ['Watchers', 'Every watcher of the task.'],
            ['Assignees and watchers', 'Both groups, as direct recipients.'],
          ],
        },
        {
          type: 'note',
          text: 'The message is **mandatory** (3 to 2000 characters): explain what you need, so the recipients read it right in the notification.',
        },
      ],
    },
    {
      id: 'subtasks-and-recurrence',
      title: 'Sub-tasks, recurrence and templates',
      blocks: [
        {
          type: 'paragraph',
          text: "From the detail, press **New sub-task** to create a child activity, with dates within the parent's range. Alternatively, while creating the task you can add one or more rows right in the form's **Sub-tasks** section: a title is enough, plus an optional due date and assignees — everything else is inherited from the parent.",
        },
        {
          type: 'paragraph',
          text: "In the detail's **Sub-tasks** panel, drag a row (by its handle) to reorder it, and each row lets you complete, reopen or delete that single sub-task, whenever your permissions allow it.",
        },
        {
          type: 'paragraph',
          text: 'With **Recurring** on, QNet creates the future occurrences by itself. Pick the frequency — Daily, Weekly, Monthly, Yearly or Custom (every N days) — the interval in **Repeat every** and the end: On a date, After a number of occurrences or Never.',
        },
        {
          type: 'paragraph',
          text: "For a Monthly or Yearly recurrence, choose whether the day is **fixed** (e.g. the 31st of the month) or **ordinal** (e.g. the 2nd Tuesday) — Yearly also asks for the month. With **Workdays only** on, the generated dates always fall Monday through Friday (no holiday calendar).",
        },
        {
          type: 'note',
          text: "Picking a **Task Template** when creating a Commessa (the **Schedule** action on the contract) creates the template's tasks and assigns them to the owners: you find them in the Commessa's Task section. Templates are configured in **Task › Task Templates**.",
        },
      ],
    },
    {
      id: 'list-editing-and-bulk',
      title: 'Quick edit and list actions',
      blocks: [
        {
          type: 'paragraph',
          text: 'The column picker lets you turn on five columns hidden by default: **Actual minutes** (the sum of everyone’s time entries), **Updated at**, **Recurring**, **Parent task** and **Phase**.',
        },
        {
          type: 'paragraph',
          text: 'Clicking an editable cell (Title, Status, Type, Priority, Importance, Start date, Due date, Requester, Assignees, Watchers, Estimated time, Work order, Phase) edits it **directly in the list**, without opening the form: the cell disables itself on a completed, in-validation or blocked task (the super-admin is the exception). **Phase** can only be picked once the row already has a work order.',
        },
        {
          type: 'note',
          text: 'Changing **Status** to a closing one opens the same **Complete** dialog as the detail; cancelling it reverts the cell to its previous value. Changing a closed status to an open one reopens the task right away, same as the Reopen action.',
        },
        {
          type: 'paragraph',
          text: 'The row carries the same actions as the detail (Complete, Reopen, Approve, Reject, Block, Unblock, Request update), plus **Duplicate** (opens the create form pre-filled with the same data — except attachments, sub-tasks, status, completion date and time entries, and with no parent task) and **Notes** (opens the task’s notes panel, with the note count on the icon’s badge).',
        },
        {
          type: 'paragraph',
          text: 'Selecting one or more rows shows the **Actions** bar: Assign (replaces the assignees), Complete, Reopen, Block, Unblock, Priority, Start date, End date, Delete.',
        },
        {
          type: 'warning',
          text: 'A bulk action is **all or nothing**: if even one selected task is not eligible (e.g. blocked, or Complete requires validation), the action stops with a message listing which tasks and why, and none of the selected tasks is changed.',
        },
        {
          type: 'paragraph',
          text: 'With permission to create tasks, the bottom of the table shows a compact row to **quickly** create one: title, type, priority, importance, status, due date, requester, assignees and watchers. Type, priority and importance start already filled with the catalog’s default entry; the starting due date, requester and assignee are, respectively, today and yourself.',
        },
        {
          type: 'note',
          text: 'The table footer shows the **total estimated minutes** of the filtered set (not just the visible page).',
        },
      ],
    },
    {
      id: 'collaboration-and-notifications',
      title: 'Collaboration and notifications',
      blocks: [
        {
          type: 'paragraph',
          text: "In the task's detail you find **Notes**, **Documents**, **History** and **Time tracking**. You receive a notification (bell and email) when:",
        },
        {
          type: 'list',
          items: [
            'a task is assigned to you as a new assignee (unless you are also its creator) or as a new watcher (always, even as the creator);',
            'a task goes to validation, or its validation is approved or rejected;',
            'a task is closed: the notification reaches the requester and the watchers, and the assignees only if there is more than one; if the task was in validation, the assignees still get a separate approval notification;',
            'a task is reopened, blocked or unblocked;',
            'someone requests an update: the chosen recipients get it, with a copy (**CC**) to the watchers whenever the request targets the assignees.',
          ],
        },
        {
          type: 'note',
          text: 'Whoever performs the action does not receive it, and neither does the creator as such (unless they are also the requester, an assignee or a watcher), nor a deactivated user.',
        },
      ],
    },
  ],
}

export default guide
