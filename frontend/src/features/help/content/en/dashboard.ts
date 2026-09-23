import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: 'The Dashboard is the first page after signing in: it sums up your open tasks, your time entries and the key figures of the modules.',
  sections: [
    {
      id: 'overview',
      title: 'What you find',
      blocks: [
        { type: 'paragraph', text: 'The **Dashboard** opens right after you press **Sign in**. It is split into blocks, top to bottom: **Activities to complete** (with the Task statistics), **Time tracking**, and the statistics of **Opportunities**, **Quotes**, **Leads** and **Registries**.' },
        { type: 'note', text: 'You only see the blocks of the modules you have permission for. If you have none, the page shows “No content available”.' },
      ],
    },
    {
      id: 'tasks-to-complete',
      title: 'Activities to complete',
      blocks: [
        { type: 'paragraph', text: 'Five tiles count the **open** tasks you can see, sub-tasks included. Each tile also shows the total **estimated time**.' },
        {
          type: 'table',
          headers: ['Tile', 'What it counts'],
          rows: [
            ['All', 'Every open task you can see.'],
            ['Assigned to me', 'The tasks you are an assignee of.'],
            ['Assigned by me', 'The tasks you requested, unless you are also an assignee.'],
            ['Created by me', 'The tasks you created, when the requester is someone else.'],
            ['Observed by me', 'The tasks you follow as a watcher.'],
          ],
        },
        { type: 'paragraph', text: 'The **Assigned by me** tile shows a **To validate** badge when some of those tasks are waiting for your validation.' },
        { type: 'steps', items: ['Press a tile: the **Tasks** list opens already filtered with the same criteria.', 'Press the **To validate** badge to see only the tasks in validation assigned by you.'] },
        { type: 'tip', text: 'The filter opened from the Dashboard applies to that visit only: the filters you saved on the Tasks list stay as they were until you press **Apply** or **Reset**.' },
        { type: 'paragraph', text: 'Below the tiles, in the same block, you find the Task figures: Overdue, Due today, Estimated and Actual. The charts by status, by priority and of new tasks per month stay in the **Statistics** panel of the Tasks list.' },
      ],
    },
    {
      id: 'time-entries',
      title: 'Time tracking',
      blocks: [
        { type: 'paragraph', text: 'Shows your time entries overview and operational pulse, as on the **Time entries** page. Pick the period with **Daily** (preset), **Weekly**, **Monthly** or **Yearly**.' },
      ],
    },
    {
      id: 'module-statistics',
      title: 'Module statistics',
      blocks: [
        { type: 'paragraph', text: 'The **Opportunities**, **Quotes**, **Leads** and **Registries** blocks show the same figures and charts as each module’s **Statistics** panel, always open.' },
        { type: 'paragraph', text: 'The **Quotes** block shows the number of quotes, **Net revenue**, **Net margin**, the **Won** quotes, the breakdown by status and the new quotes per month.' },
      ],
    },
  ],
}

export default guide
