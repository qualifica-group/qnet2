import type { HelpSection } from '../../../types'

/** Recurring-email section of the work-orders guide, split out to stay under the file-size limit. */
export const recurringEmailSections: HelpSection[] = [
    {
      id: 'recurring-emails',
      title: 'Recurring emails',
      blocks: [
        {
          type: 'paragraph',
          text: "In the work order's **Email** tab you can prepare an email once and have it **resent automatically**, identical, on every date you choose. It only works for work order emails.",
        },
        {
          type: 'steps',
          items: [
            'Press **New email** and fill in recipients, subject, body and attachments as usual.',
            'Turn on the **Repeat this email** switch.',
            'Pick the frequency (daily, weekly, monthly, yearly), how many days, weeks, months or years apart, and the days to send on. With **Workdays only** a send that would fall on a non-working day moves to the next working day.',
            'Set the **first send** date and the **end date**: the end date is required. Below the fields you see the rule written as a sentence.',
            'Press **Schedule** (instead of Send). The email does not go out right away: the first send happens on the day you chose.',
          ],
        },
        {
          type: 'note',
          text: 'Sends go out at **8:00** and are **identical** to the scheduled email: same recipients, subject, body and attachments, with no recalculation of template placeholders. Each send shows up in the history as a normal sent email, with a repeat icon.',
        },
        {
          type: 'paragraph',
          text: 'The series **stops by itself** when the end date arrives, when the work order becomes **Completed** or **Closed**, or if whoever scheduled the email is deactivated. A scheduled email shows the **Scheduled** status; once the series is over it becomes **Schedule ended**, with the reason in the detail.',
        },
        {
          type: 'tip',
          text: 'To **stop or change** the series, open the email and press **Back to draft** (only whoever scheduled it). It returns to a draft with the rule already set: you can change it and schedule it again, or delete it. Sends already made stay in the history.',
        },
        {
          type: 'note',
          text: 'If a send is missed because the system was down, it is not made up: when it restarts a single send goes out and the series continues from the next date. A failed send does not stop the series and can be resent by hand from its detail.',
        },
      ],
    },
]
