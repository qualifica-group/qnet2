import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'formalab',
  title: 'Formalab',
  summary:
    'Formalab connects QNet to the course management system: the class catalogue is synced automatically and the work orders of categories linked to Formalab receive the edition data in the fields chosen in the category map.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › External services › Formalab** and is reserved to the **super-admin** role. The menu item, the page, the category section and the work order tab appear **only if the integration is active** on your installation.',
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
            '**Mapped fields not found**: appears only when a work order field used in a category map no longer exists among the attributes, because its code was renamed or deleted in Configuration › Attributes. It lists the missing codes: those fields are not updated until the code is restored or the map fixed.',
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
      id: 'category-settings',
      title: 'Linking a category to Formalab',
      blocks: [
        {
          type: 'paragraph',
          text: 'Which work orders connect to Formalab is decided by the **product category**. In the category detail (Configuration › Product categories) the **Formalab** section holds two settings.',
        },
        {
          type: 'list',
          items: [
            '**Linked to Formalab**: decided on the **root category** and valid for all its subcategories, which show it as "Inherited from …" and cannot change it.',
            '**Field map**: for each Formalab field (course / edition name, status, dates, hours, decrees, site, learners…) choose the work order field to write it into. The list offers only the category\'s work order fields of a compatible type; fields left "Not linked" are not written.',
            'The **course / edition name** is required: it is the field the work order finds its Formalab edition from.',
            'A subcategory uses the map of the category above ("Map inherited from …"); turning on **Own map** lets it define a different one, and turning it off makes it inherit again.',
            'On the work orders of linked categories the mapped fields are **read-only**: only Formalab updates them, and a sync icon appears beside the field name (hover it to read that the value comes from Formalab). The **course / edition name** field is the exception and stays editable, because it is used to find the edition.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Open the root category and, in the **Formalab** section, click **Edit**.',
            'Turn on **Linked to Formalab**.',
            'Check the map: if the category has the GOL course fields, they are already proposed. Choose or fix the field for each data item you want to receive.',
            'Click **Save**.',
          ],
        },
        {
          type: 'note',
          text: 'Editing the section requires the permission to edit the category. If a category is unlinked, its already synced work orders stop updating and their tab shows the reason.',
        },
      ],
    },
    {
      id: 'work-order-tab',
      title: 'The Formalab tab on the work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'On work orders of a category linked to Formalab, or that are already synced, the **Formalab** tab appears. The edition is never picked by hand: the system finds it from the work order field mapped on the **course / edition name** (for GOL work orders the **course name**), which must be written exactly as on Formalab ("code - title"). Once synced, the tab shows the class data: status, dates, hours, site, learners and last update.',
        },
        {
          type: 'steps',
          items: [
            'Make sure the work order\'s **course name** is filled in.',
            'Open the **Formalab** tab and click **Sync**.',
            'The system looks up in the Formalab catalogue the edition with the same name, re-reads it from Formalab right away and copies its data into the fields mapped by the category. From then on the work order also updates by itself every 10 minutes.',
            'If you change the course name, click **Sync** again: the system moves to the edition with the new name.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Work order cannot be linked**: its category is not linked to Formalab, or the map does not name the course name field.',
            '**Empty course name**: fill in the course name first.',
            '**No edition with this name**: the name differs from the one on Formalab, or the edition is new and the catalogue has not received it yet (it updates every 10 minutes).',
            '**Several editions with this name**: the name is not unique on Formalab and the system cannot choose.',
          ],
        },
        {
          type: 'note',
          text: 'Syncing requires the permission to edit the work order.',
        },
      ],
    },
    {
      id: 'automatic-sync',
      title: 'Automatic sync',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every **10 minutes** QNet rereads the class catalogue. If a class linked to a work order has changed, the work order\'s mapped fields are updated; if nothing changed, the work order is left alone.',
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
            'If you click **Sync** while Formalab is unreachable, the work order fields are **not touched** and you see the error message.',
            'If the code of a field used in a map is renamed or deleted, that field stops updating: the Formalab card of **System health** turns Degraded with the **Mapped fields** check, and the sync history records it.',
            'The integration status also appears on the **System health** page.',
          ],
        },
      ],
    },
  ],
}

export default guide
