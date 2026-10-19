import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'time-entries',
  title: 'Time tracking',
  summary: 'Time tracking logs the time spent on work. Time is entered by hand: there is no stopwatch.',
  sections: [
    {
      id: 'overview',
      title: 'What time tracking is',
      blocks: [
        {
          type: 'paragraph',
          text: 'It lives under the **Time tracking** menu entry.',
        },
      ],
    },
    {
      id: 'recording-time',
      title: 'Log time',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New time entry**: the record opens with its rows closed.',
            'On each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts it back as it was.',
            'Press **Add**: it checks every row and creates the time entry.',
          ],
        },
        {
          type: 'table',
          headers: ['Field', 'Notes'],
          rows: [
            ['Title, Date, Type', 'Required (type: for example meeting or call).'],
            ['Schedule: From / To', 'Optional, but if you set one you need both.'],
            ['Schedule: Time', 'Minutes, from 1 to 1440; calculated from the times, editable by hand.'],
            ['Notes', 'Optional.'],
            ['Links: Customer, Opportunity, Work order, Activity', 'Optional, all on a single row.'],
            ['Phase', 'Shown only with a Work order and no Task: one of the work order\'s open phases, or none.'],
          ],
        },
        {
          type: 'warning',
          text: "If you link a task in the **Activity** field, title, customer, opportunity, work order and phase are taken from the task.",
        },
        {
          type: 'note',
          text: 'The phase follows the task: an entry linked to a task sits in that task\'s phase (for a sub-task, the main task\'s phase). If the task later moves to another phase, or is moved under a different task, all of its entries — including the sub-tasks\' — move to the new phase too. Entries with no task keep the phase chosen by hand. Entries logged before this change stay as they are until their task changes phase for the first time: from that point on they line up too. In the work order\'s Tasks tab, next to each phase, you find the **Logged minutes** from time entries, plus the **No phase** total for entries without one.',
        },
      ],
    },
    {
      id: 'from-a-task',
      title: 'Log time from a task',
      blocks: [
        {
          type: 'tip',
          text: "You can also log time from the task's detail: the **Time tracking** tab, fill in **New interval** and press **Add time entry**.",
        },
      ],
    },
    {
      id: 'reviewing-and-editing',
      title: 'Review and edit',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Period** card offers the **Daily**, **Weekly**, **Monthly**, **Yearly** or **Custom range** views. Below you find:',
        },
        {
          type: 'list',
          items: [
            '**Overview**: period target, tracked time, average focus and anomalies.',
            '**Operational pulse**: coverage and the most frequent activity types.',
            'The list of days, each On target, Under target, Over target or No target.',
          ],
        },
        {
          type: 'paragraph',
          text: "Expand a day to see its entries and add a note for the day. On every entry use **Open** (or click the title), **Delete** or Change type. Filters and Sorting narrow and sort the days; Export downloads the filtered report or a user's monthly report.",
        },
        {
          type: 'paragraph',
          text: 'An entry has no edit form: each row edits in place. Press the pencil (or the value), change the field and press **Save**; **Cancel** or Esc leave the row as it was. Schedule (From, To, Time) and Links (Customer, Opportunity, Work order, Phase, Activity) each edit on a single row. With an Activity linked the Title cannot be edited, the task sets it. **Delete**, at the bottom of the record, removes the entry after a confirmation. Without the edit permission the rows show no pencil.',
        },
      ],
    },
    {
      id: 'team-view',
      title: 'Team view and daily target',
      blocks: [
        {
          type: 'tip',
          text: 'Managers can switch from **Personal view** to **My team** and view their collaborators\' data read-only. A collaborator who reports to several managers appears under each of them. Next to each collaborator you see their **Job roles** (separated by commas when there are several).',
        },
        {
          type: 'note',
          text: 'A working day\'s target is the standard daily duration minus the daily break duration, set on the user profile; on non-working days the target is zero. If the user profile does not set them, a default target applies.',
        },
      ],
    },
    {
      id: 'absences-and-target',
      title: 'Absences and target',
      blocks: [
        {
          type: 'paragraph',
          text: 'Approved absences (see the **Absences** guide) change the day\'s target: with a full-day absence the target is zero; with an absence by hours the target is reduced by those hours. The day card shows the absence type and hours.',
        },
        {
          type: 'note',
          text: 'Requests still waiting for approval do not count: the target changes only once the request is approved.',
        },
      ],
    },
  ],
}

export default guide
