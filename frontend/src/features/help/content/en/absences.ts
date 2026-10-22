import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absences',
  title: 'Absences',
  summary: 'Request leave, time off and other absences seeing duration and balance straight away; your manager approves them, also in bulk, with the team in view.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Absences** in the menu. Here you request an absence, follow the outcome and check how many hours you have left for each type.',
        },
        {
          type: 'list',
          items: [
            'Like the Time tracking, Absences is open to **every user**: everyone sees their own absences.',
            'A **manager** also sees the absences of the people who report to them, also indirectly.',
            'Only those with the permission see **everyone’s** absences: HR and the administrators.',
          ],
        },
      ],
    },
    {
      id: 'summary',
      title: 'The summary',
      blocks: [
        {
          type: 'paragraph',
          text: 'At the top of the **Requests** view you find **My summary**: your **upcoming absences** (a click opens the detail) and how many **pending requests** are waiting for a decision.',
        },
        {
          type: 'paragraph',
          text: 'If there are requests you have to decide, the notice "You have N requests to approve" appears with a button to go there, and the **Absences** menu item shows the same number (also with the menu collapsed).',
        },
        {
          type: 'note',
          text: 'Durations and balances read in **days and hours** (for example "1 d 2 h"), based on your daily hours.',
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
            ['To approve', 'Submitted requests that you are the one to decide, with their number next to the name. HR sees every submitted request except its own.'],
            ['All', 'The requests of your team and your own. HR sees everyone’s.'],
          ],
        },
        {
          type: 'tip',
          text: 'If you have requests to approve, the page opens straight on the **To approve** tab.',
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
            'In the **Absence** section choose the **Type**, then the mode: **Full day** (with **From** and **To**), **Half day** (one day, **Morning** or **Afternoon**) or **Hourly** (one day, with the duration). You only see the modes the type allows.',
            'Check the **preview** below the dates (see below).',
            'In the **Notes and attachment** section add, if needed, a **Note** and a file (pdf, jpg or png), for example a certificate.',
            'Press **Save draft** to continue later, or **Send** to send it to your manager. The buttons are at the top right and repeated at the bottom of the form.',
          ],
        },
        {
          type: 'table',
          headers: ['In the preview', 'What it tells you'],
          rows: [
            ['Working days and duration', 'What the request is worth, in days and hours. Weekends, holidays and company closures are excluded and listed with the reason.'],
            ['Current balance and after', 'What you have left of the chosen type today and after this request. The balance never blocks submitting: your manager decides.'],
            ['Short notice', 'The type asks for a minimum notice and the request comes later than that. You can still send it: your manager sees it flagged.'],
            ['Overlaps', 'You already have an active absence on those dates: change the period, otherwise sending fails.'],
            ['Colleagues away', 'Colleagues of your function or with your same manager already away on those dates, among those you can see in the calendar.'],
          ],
        },
        {
          type: 'note',
          text: 'A half day is worth half of your daily hours. HR can enter a request **on behalf** of another person by choosing them in the **Person** field.',
        },
      ],
    },
    {
      id: 'detail',
      title: 'The detail',
      blocks: [
        {
          type: 'paragraph',
          text: 'Click a request to open its detail. At the top you find the absence type (with its colour), the person, the status, the **Short notice** and **Collective leave** marks when they apply and, on the right, only the buttons of the actions you can take (Edit, Send, Approve, Reject, Cancel, Delete).',
        },
        {
          type: 'table',
          headers: ['Section', 'What it holds'],
          rows: [
            ['Request', 'Period (full day, half day with morning or afternoon, hourly), duration in days and hours, sending date and note.'],
            ['Approval', 'Approvers, who decided and when, rejection reason.'],
            ['Context', 'Only for whoever can approve or reject: the person’s balance before and after the request, notice and colleagues already away in the same period.'],
            ['Attachment', 'The attached file, to download, upload or remove.'],
          ],
        },
      ],
    },
    {
      id: 'quick-approval',
      title: 'Approving quickly',
      blocks: [
        {
          type: 'list',
          items: [
            'In the **To approve** tab every row has the **Approve** (with confirmation) and **Reject** (asks for the reason) buttons: no need to open the detail.',
            'To decide several requests at once select them with the checkboxes and use **Approve selected** or **Reject selected** (one reason for all).',
            'At the end a message tells how many requests were decided and which were not, with the reason (for example already decided by someone else).',
          ],
        },
        {
          type: 'note',
          text: 'Every decision, single or multiple, sends the same notifications and stays in the history.',
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
          type: 'paragraph',
          text: 'Besides the manager, **HR** can approve or reject any submitted request except its own.',
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
        {
          type: 'list',
          items: [
            '**Submission**: the approvers of the request.',
            '**Approval or rejection**: the person who asked for the absence (with the reason, if rejected) and the other approvers.',
            '**Cancellation**: the person who asked for the absence and the approvers.',
            '**Type needing no approval**: the person who asked for the absence and the approvers.',
          ],
        },
        {
          type: 'note',
          text: 'HR gets a copy of every step. If someone other than the manager decides, the manager is told the outcome.',
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
          text: 'The **Calendar** view is picked at the top right, next to **Requests** and the "New request" button: it shows month by month who is absent: one row per person, one column per day. Weekends and holidays are highlighted, holidays have their number in red.',
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
          type: 'paragraph',
          text: 'Every role already has the **own and team** calendar: someone nobody reports to sees only themselves. The **everyone** calendar belongs to HR and the administrators.',
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
