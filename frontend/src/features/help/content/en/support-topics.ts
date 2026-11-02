import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'support-topics',
  title: 'Technical support',
  summary: 'The problem types users can pick when they ask the IT team for help.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is set up in **Configuration › Task › Technical support**. Each entry is a **problem type** (for example Printer, Password, Slow PC) shown in the **Technical support** window of the header. When a user sends a request, QNet creates a ready-made task by itself using the values of that type.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters. It is the name users see.'],
            ['Description', 'Optional. A short help text shown under the name.'],
            ['Icon', 'Optional. Shown on the type button.'],
            ['Order', 'Sets the position in the list users see.'],
            ['Active', 'If you turn it off, the type disappears from the users’ window.'],
            ['Type, Category, Priority, Importance', 'Values the created task will already have.'],
            ['Priority when urgent', 'Optional. Replaces the normal priority when the user says it is urgent.'],
            ['Due in / Due in when urgent', 'Calendar days from today to the task due date. If the urgent field is empty the normal days apply.'],
            ['Private task, Closure feedback required', 'Settings the created task will already have on or off.'],
            ['Assignees', 'Required: at least one user who receives the task and the notification.'],
            ['Watchers', 'Optional. They cannot also be assignees.'],
          ],
        },
        {
          type: 'warning',
          text: 'A type without at least one active assignee is not shown to users. If no type is active, the window says that support is not configured yet.',
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
            'Press **New topic**.',
            'For each row press the pencil, fill it in and press **Done**; then press **Save**.',
            'To edit, open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved).',
            'To hide it from users without losing it, turn **Active** off.',
          ],
        },
        {
          type: 'note',
          text: 'Deleting a type does not touch the tasks already created: they stay as they were.',
        },
      ],
    },
  ],
}

export default guide
