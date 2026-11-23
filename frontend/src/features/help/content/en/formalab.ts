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
          text: 'The pages are in the **External integrations › Formalab** menu (**Status and settings** and **Classes**) and are reserved to the **super-admin** role. The menu items, the pages, the category section and the work order tab appear **only if the integration is active** on your installation.',
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
            '**Unlinked work orders**: the work orders of the linked categories the sync could not link, by reason: chosen class not in the catalogue (the chosen edition has not been read from Formalab yet), no Formalab class chosen. **Linked at the next run** counts those whose chosen class is in the catalogue and the next sync will link. To link one, choose the class in the work order\'s **Course / edition name** field and save: the link is immediate.',
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
            ['**Sync now**', 'Starts the sync right away: it rereads the class catalogue, aligns the work orders to the edition chosen in their select (matched by edition ID only) and updates the fields of the linked work orders. The status refreshes a few seconds after the click.'],
            ['**Test connection**', 'Tries to reach the service and shows the response time or the reason for the error.'],
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
      id: 'classes-page',
      title: 'The Classes page',
      blocks: [
        {
          type: 'paragraph',
          text: 'Under **External integrations › Formalab › Classes** you find the **class catalogue** read from Formalab, read-only: edition ID, code, title, class and course status, dates, hours, site, learners and last read. It is the same list the work order picks its class from. **Sync now** starts reading the catalogue right away (the sync is queued: reload the table after a few seconds).',
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
            '**Field map**: for each Formalab field (class, status, dates, hours, decrees, site, learners…) choose the work order field to write it into. The list offers only the category\'s work order fields of a compatible type; fields left "Not linked" are not written.',
            'The **Formalab class (edition)** is required and can only be mapped to a **relation field towards the Formalab classes**: it is the field the work order finds its edition from.',
            'A subcategory uses the map of the category above ("Map inherited from …"); turning on **Own map** lets it define a different one, and turning it off makes it inherit again.',
            'On the work orders of linked categories the mapped fields are **read-only**: only Formalab updates them, and a sync icon appears beside the field name (hover it to read that the value comes from Formalab). The **course / edition name** field is the exception and stays editable, because it is the select you choose the class with.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Open the root category and, in the **Formalab** section, click **Edit**.',
            'Turn on **Linked to Formalab**.',
            'Check the map: if the category has the GOL course fields, they are already proposed (the course / edition name must be a relation field towards the Formalab classes). Choose or fix the field for each data item you want to receive.',
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
          text: 'On work orders of a category linked to Formalab, or that are already synced, the **Formalab** tab appears. The class is chosen in the work order\'s **course / edition name** field (for GOL work orders the **course name**): a select on the Formalab classes, searchable by code, title or edition ID. Matching is by **edition ID** only. Once the class is chosen, the tab shows the class data: status, dates, hours, site, learners and last update.',
        },
        {
          type: 'steps',
          items: [
            'Choose the class in the work order\'s **course / edition name** field and save: the link is immediate and the mapped fields are filled from the catalogue.',
            'Open the **Formalab** tab to see the class data.',
            'Click **Sync** to re-read the chosen edition from Formalab right away and rewrite the mapped fields. From then on the work order also updates by itself every 10 minutes.',
            'If you change the class, save: the link moves to the new edition. If you clear the select, the link is removed.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Work order cannot be linked**: its category is not linked to Formalab, or the map does not name the course / edition field.',
            '**No class chosen**: choose the class in the **course / edition name** field first (clicking **Sync** with an empty select shows "Choose the Formalab class").',
            '**Class not in the catalogue**: the edition is new and the catalogue has not received it yet (it updates every 10 minutes, or use **Sync now** on the Classes page).',
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
          text: 'Every **10 minutes** QNet rereads the class catalogue and links the work orders of the linked categories whose chosen edition is now in the catalogue (always by edition ID). If a class linked to a work order has changed, the work order\'s mapped fields are updated; if nothing changed, the work order is left alone. When you choose, change or clear a work order\'s class, the link is updated as soon as you save.',
        },
        {
          type: 'paragraph',
          text: 'If a class **no longer exists on Formalab** (it is no longer in the catalogue, or Formalab answers that it does not exist when **Sync** or a notification rereads it), QNet removes it from the catalogue, **deletes the link** of its work orders and **clears the chosen class** in their select; the other work order fields keep their last values. The number of removed classes shows in **Last sync**. If Formalab returns an empty or incomplete catalogue nothing is removed.',
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
