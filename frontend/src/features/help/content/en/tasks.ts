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
          text: 'They live in **Task › Tasks**. You see a task if you created or requested it, if you are an assignee or a watcher, or if you have permission to see every task.',
        },
        {
          type: 'tip',
          text: 'The **Completion** percentage depends on the status chosen and is never entered by hand.',
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
            ['Status', 'Open (preset), Completed, Blocked or All.'],
            ['Due', 'Today, Overdue or This week, on the end date (or the start date when missing).'],
            ['Assignment', 'The tasks assigned to you or requested by you.'],
          ],
        },
        {
          type: 'tip',
          text: 'The table opens on the **open** tasks only: pick **All** in the Status filter to see the closed ones too.',
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
            ['Task', 'Title (required), Description, Parent task.'],
            ['Classification', 'Status (required), Type, Category, Priority, Importance.'],
            ['Account and contact', "Account, Contact (among the account's own)."],
            ['People', 'Requested by (required), Assignees (at least one), Watchers.'],
            ['Scheduling', 'Start date, Due date (required), times, Estimated time (minutes).'],
            ['Linked records', 'Opportunity or Work order; with a Work order, the Phase to place the task in (open phases only, not for subtasks).'],
            ['Closure', 'Feedback required, Validation.'],
            ['Recurrence', 'Frequency and end of the repetition.'],
          ],
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
          text: 'Every status belongs to a phase: Open, Pending, To validate, Closed (positive outcome), Closed (negative outcome). A task in validation or closed does not accept changes to its main data.',
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
            ['Reopen', 'Moves the task back to "In progress".'],
            ['Approve', 'Definitively closes a task in validation.'],
            ['Reject', 'Moves a task in validation back to "In progress".'],
            ['Block / Unblock', 'Suspends or reactivates the task.'],
            ['Request update', 'Sends an email and a notification to the chosen assignees and watchers.'],
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
      ],
    },
    {
      id: 'subtasks-and-recurrence',
      title: 'Sub-tasks, recurrence and templates',
      blocks: [
        {
          type: 'paragraph',
          text: "From the detail, press **New sub-task** to create a child activity, with dates within the parent's range.",
        },
        {
          type: 'paragraph',
          text: 'With **Recurring** on, QNet creates the future occurrences by itself. Pick the frequency (Daily, Weekly, Monthly), the interval in **Repeat every** and the end: On a date, After a number of occurrences or Never.',
        },
        {
          type: 'note',
          text: "Picking a **Task Template** when creating a Commessa (the **Schedule** action on the contract) creates the template's tasks and assigns them to the owners: you find them in the Commessa's Task section. Templates are configured in **Task › Task Templates**.",
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
            'a task is assigned to you, as an assignee or a watcher;',
            'a task goes to validation, is approved or is rejected;',
            'a task is closed, reopened, blocked or unblocked;',
            'someone requests an update from you.',
          ],
        },
        {
          type: 'note',
          text: 'Whoever performs the action does not receive the notification.',
        },
      ],
    },
  ],
}

export default guide
