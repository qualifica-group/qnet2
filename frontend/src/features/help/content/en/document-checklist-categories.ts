import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-checklist-categories',
  title: 'Checklist categories',
  summary: 'Categories to group document checklists, synchronized with ArchivioSmart.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Configuration › Documents › Checklist categories**. A category groups document checklists; in **ArchivioSmart** it is used to filter checklists and requests. Every save is sent to ArchivioSmart automatically, always before the checklists that use it.',
        },
      ],
    },
    {
      id: 'category-fields',
      title: 'Category fields',
      blocks: [
        {
          type: 'list',
          items: [
            '**Name**: required, at most 255 characters; it cannot match another category (upper and lower case do not matter).',
            '**Description**: free note, at most 2000 characters.',
            '**Order**: integer from 0 to 65535 (default 0); categories are listed from the lowest number.',
          ],
        },
        {
          type: 'paragraph',
          text: 'A checklist can have a single category, chosen in the **Category** field of its form.',
        },
      ],
    },
    {
      id: 'list',
      title: 'The list',
      blocks: [
        {
          type: 'paragraph',
          text: 'Columns: name, description, order, number of checklists, sync, last sync, created on. The sync status can be:',
        },
        {
          type: 'list',
          items: [
            '**Synced**: ArchivioSmart is up to date.',
            '**Error**: hover or focus the badge to read the error.',
            '**Pending**: sending in progress; the status updates when you reload the page.',
          ],
        },
      ],
    },
    {
      id: 'actions',
      title: 'Actions',
      blocks: [
        {
          type: 'list',
          items: [
            '**New category**: creates a category.',
            '**Sync again** (row): resends the category to ArchivioSmart.',
            '**Delete** (row): only possible when the category no longer contains checklists; the category is also removed from ArchivioSmart.',
          ],
        },
        {
          type: 'paragraph',
          text: 'If the category still contains checklists, the deletion is refused with a message telling how many there are: move them to another category or delete them first. For a first alignment of all categories use **Sync all** on the checklists page.',
        },
      ],
    },
    {
      id: 'sync-log',
      title: 'Sync details',
      blocks: [
        {
          type: 'paragraph',
          text: 'In a category detail, the **Synchronizations** section shows the current status, the last error and the latest 20 calls to ArchivioSmart with date, operation, outcome, HTTP code and message.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Common errors',
      blocks: [
        {
          type: 'list',
          items: [
            '**Name already used in ArchivioSmart**: a category created directly in ArchivioSmart has the same name; choose another name.',
            '**Category not synchronized** on a checklist: the checklist is not sent until its category is aligned; fix the category and use **Sync again**.',
            '**Connection not configured** or **invalid token**: see the document checklists guide.',
          ],
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'Categories use the same permissions as document checklists: **view**, **create**, **edit** and **delete**. There are no dedicated permissions.',
        },
      ],
    },
  ],
}

export default guide
