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
            'Press **New time entry**.',
            'Fill in the fields.',
            'Press **Add**.',
          ],
        },
        {
          type: 'table',
          headers: ['Field', 'Notes'],
          rows: [
            ['Title, Date, Type', 'Required (type: for example meeting or call).'],
            ['From / To', 'Optional, but if you set one you need both.'],
            ['Time', 'Minutes, from 1 to 1440; calculated from the times, editable by hand.'],
            ['Notes', 'Optional.'],
            ['Customer, Opportunity, Work order, Task', 'Optional links.'],
          ],
        },
        {
          type: 'warning',
          text: "If you link a task in the **Task** field, title, customer, opportunity and work order are taken from the task.",
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
          text: "Expand a day to see its entries and add a note for the day. On every entry use **Edit**, **Delete** or Change type, then update. Filters and Sorting narrow and sort the days; Export downloads the filtered report or a user's monthly report.",
        },
      ],
    },
    {
      id: 'team-view',
      title: 'Team view and daily target',
      blocks: [
        {
          type: 'tip',
          text: 'Managers can switch from **Personal view** to **My team** and view their collaborators\' data read-only.',
        },
        {
          type: 'note',
          text: 'A working day\'s target is the standard daily duration minus the daily break duration, set on the user profile; on non-working days the target is zero. If the user profile does not set them, a default target applies.',
        },
      ],
    },
  ],
}

export default guide
