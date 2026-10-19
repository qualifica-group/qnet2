import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absences',
  title: 'Absences',
  summary: 'Request leave, time off and other absences; your manager approves them and you see your balances in hours.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Absences** in the menu. Here you request an absence, follow the outcome and check how many hours you have left for each type.',
        },
      ],
    },
    {
      id: 'tabs',
      title: 'The tabs',
      blocks: [
        {
          type: 'table',
          headers: ['Tab', 'What it shows'],
          rows: [
            ['Mine', 'Your own requests.'],
            ['To approve', 'Submitted requests that you are the one to decide.'],
            ['All', 'The requests of your team and your own. HR sees everyone’s.'],
          ],
        },
      ],
    },
    {
      id: 'new-request',
      title: 'New request',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press "New request".',
            'Choose the absence **Type** and whether it is **full day** or **by hours** (hours apply to a single day).',
            'Enter **From** and **To** (for hours, a single day) and, if needed, a **Note**.',
            'If you like, attach a file (pdf, jpg or png), for example a certificate.',
            'Press **Save draft** to continue later, or **Submit** to send it to your manager.',
          ],
        },
        {
          type: 'paragraph',
          text: 'The form shows the remaining balance of the chosen type and, for hourly requests, how many hours you would have left after it. The balance never blocks submitting: your manager decides.',
        },
        {
          type: 'note',
          text: 'For full days the duration is calculated when you submit: only working days are counted (weekends and holidays excluded) and multiplied by your daily hours. You cannot overlap two requests that are still active.',
        },
      ],
    },
    {
      id: 'statuses',
      title: 'Request statuses',
      blocks: [
        {
          type: 'table',
          headers: ['Status', 'Meaning'],
          rows: [
            ['Draft', 'Not submitted yet: you can edit and delete it.'],
            ['Submitted', 'Waiting for your manager’s decision.'],
            ['Approved', 'Accepted: the hours count as taken.'],
            ['Rejected', 'Turned down, with the reason given by the manager.'],
            ['Cancelled', 'Withdrawn: it no longer counts.'],
          ],
        },
        {
          type: 'tip',
          text: 'Types that do not need approval (for example Sick leave) are approved straight away; your manager is notified.',
        },
      ],
    },
    {
      id: 'approvers',
      title: 'Who approves',
      blocks: [
        {
          type: 'list',
          items: [
            'Your **direct manager**; if you have more than one, any one of them is enough.',
            'If you have none, the **manager of the function** you belong to.',
            'As a last resort, **HR**.',
          ],
        },
        {
          type: 'note',
          text: 'Nobody approves their own requests.',
        },
      ],
    },
    {
      id: 'cancelling',
      title: 'Cancelling, editing, deleting',
      blocks: [
        {
          type: 'list',
          items: [
            'You can cancel a **submitted** or **approved** request that has not started yet.',
            'HR can cancel a submitted or approved request at any time.',
            '**Drafts** can be edited and deleted; other requests cannot.',
          ],
        },
      ],
    },
    {
      id: 'notifications',
      title: 'Notifications',
      blocks: [
        {
          type: 'paragraph',
          text: 'At every step (submission, approval, rejection, cancellation) the people involved get an in-app notification and an email. Whoever performs the action receives nothing.',
        },
      ],
    },
    {
      id: 'balances',
      title: 'Balances',
      blocks: [
        {
          type: 'paragraph',
          text: 'Balances are in hours, per absence type and year, and only for types that draw on a balance.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['Entitlement', 'The annual hours you are entitled to.'],
            ['Carried over', 'The remainder from the previous year.'],
            ['Accrued', 'Hours earned so far: 1/12 of the entitlement per month; a month counts if you are employed for at least 15 days.'],
            ['Taken', 'The hours of approved requests.'],
            ['Pending', 'The hours of submitted requests not yet decided.'],
            ['Remaining', 'Carried over + Accrued − Taken.'],
          ],
        },
        {
          type: 'note',
          text: 'The remainder can go below zero: your manager decides whether to approve. On 1 January the remainder moves to the new year automatically. Only HR sets entitlements and carry-over.',
        },
      ],
    },
    {
      id: 'calendar',
      title: 'The calendar',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Calendar** view, next to **Requests**, shows month by month who is absent: one row per person, one column per day. Weekends and holidays are highlighted, holidays have their number in red.',
        },
        {
          type: 'table',
          headers: ['Symbol', 'Meaning'],
          rows: [
            ['Filled box with a check', 'Approved absence.'],
            ['Dashed box with a clock', 'Submitted request, waiting for a decision.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Who you see depends on the permissions of your role:',
        },
        {
          type: 'table',
          headers: ['Permission', 'Who you see'],
          rows: [
            ['Calendar: own only', 'Only yourself.'],
            ['Calendar: own and team', 'You and the people who report to you, also indirectly.'],
            ['Calendar: everyone', 'Every active person.'],
          ],
        },
        {
          type: 'list',
          items: [
            'The arrows move to the previous or next month; "Current month" brings you back to today.',
            'When you see more people, you can filter them by business function.',
            'Hovering a box shows type, duration and status. If you can already see that request under Requests, a click opens its detail.',
          ],
        },
        {
          type: 'note',
          text: 'Without any of the three permissions the Calendar view does not appear. Drafts, rejected and cancelled requests are not in the calendar.',
        },
      ],
    },
  ],
}

export default guide
