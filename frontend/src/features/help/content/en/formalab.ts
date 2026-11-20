import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'formalab',
  title: 'Formalab',
  summary:
    'Formalab connects QNet to the course management system: the class catalogue is synced automatically and GOL work orders linked to an edition receive the course data.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › External services › Formalab** and is reserved to the **super-admin** role. The menu item, the page and the work order tab appear **only if the integration is active** on your installation.',
        },
      ],
    },
    {
      id: 'status-card',
      title: 'The status cards',
      blocks: [
        {
          type: 'list',
          items: [
            '**Configuration**: service address, whether credentials and inbound notifications are configured, how often the sync runs, classes in the catalogue and linked work orders.',
            '**Last sync**: outcome (running, succeeded, failed), trigger, start and end times, classes and work orders updated and any error message.',
            '**Calls in the last 24 hours**: total, failed, inbound and outbound.',
            '**Course fields not found**: appears only when one of the 12 course flexible fields no longer exists among the attributes, because its code was renamed or deleted in Configuration › Attributes. It lists the missing codes: those fields are not updated until the code is restored.',
          ],
        },
        {
          type: 'note',
          text: 'Credentials are never shown: you only see whether they are configured (Yes/No).',
        },
      ],
    },
    {
      id: 'actions',
      title: 'The actions',
      blocks: [
        {
          type: 'table',
          headers: ['Button', 'What it does'],
          rows: [
            ['**Sync now**', 'Starts the class catalogue sync right away; the status refreshes a few seconds after the click.'],
            ['**Test connection**', 'Tries to reach the service and shows the response time or the reason for the error.'],
            ['**Link work orders**', 'Automatically links the work orders not yet linked whose course matches exactly one class of the catalogue. It shows how many were linked, how many are ambiguous and how many have no match.'],
          ],
        },
      ],
    },
    {
      id: 'call-log',
      title: 'Call log',
      blocks: [
        {
          type: 'paragraph',
          text: 'Under the cards is the table of **every call** to and from Formalab: date, direction, method, address, response code, outcome, duration, trigger and any error. You can filter by direction, outcome and date. Data older than 90 days is removed automatically.',
        },
      ],
    },
    {
      id: 'work-order-tab',
      title: 'The Formalab tab on the work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'On work orders that carry the GOL course fields, or that are already linked, the **Formalab** tab appears. When the work order is linked it shows the class data: status, dates, hours, site, learners and last update.',
        },
        {
          type: 'steps',
          items: [
            'Open the work order\'s **Formalab** tab.',
            'Search the edition by code, course or city and pick it from the list: it is the Formalab class catalogue, updated every 10 minutes.',
            'Click **Link**: the work order\'s course fields are filled with the class data.',
            'Once linked, **Sync** re-reads the edition from Formalab right away and updates the course fields, without waiting for the automatic update.',
            'To change edition use **Change edition**; to stop the link use **Unlink** (the fields keep the last values received).',
          ],
        },
        {
          type: 'note',
          text: 'Linking, syncing, changing and unlinking require the permission to edit the work order.',
        },
      ],
    },
    {
      id: 'automatic-sync',
      title: 'Automatic sync',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every **10 minutes** QNet rereads the class catalogue. If a class linked to a work order has changed, the work order\'s course fields are updated; if nothing changed, the work order is left alone.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'What happens on errors',
      blocks: [
        {
          type: 'list',
          items: [
            'If Formalab is unreachable the sync is marked **failed**, the error is in the call log and the catalogue stays as of the last successful sync; it retries on the next run.',
            'If updating a single work order fails, its tab shows a warning with the reason and the other work orders are updated normally.',
            'When you link an edition while Formalab is unreachable, the link is **not saved** and you see the error message.',
            'If the code of one of the course fields is renamed or deleted, that field stops updating: the Formalab card of **System health** turns Degraded with the **Course fields** check, and the sync history records it.',
            'The integration status also appears on the **System health** page.',
          ],
        },
      ],
    },
  ],
}

export default guide
