import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-checklists',
  title: 'Document checklists',
  summary: 'Lists of documents to collect, synced with ArchivioSmart.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is found in **Configuration › Documents › Document checklists**. A checklist defines the documents to collect. Every save is sent automatically to **ArchivioSmart**, which handles collecting and AI-validating the documents. The checklist **code** will be used to create document requests.',
        },
      ],
    },
    {
      id: 'checklist-fields',
      title: 'Checklist fields',
      blocks: [
        {
          type: 'list',
          items: [
            '**Name**: the name shown in lists.',
            '**Code**: letters, digits, _ and - only; saved in uppercase and unique. It can no longer be changed after the first successful sync.',
            '**Description**: free note.',
            '**Active**: to stop using a checklist, deactivating it is better than deleting it.',
          ],
        },
      ],
    },
    {
      id: 'items',
      title: 'Items',
      blocks: [
        {
          type: 'paragraph',
          text: 'A checklist can have up to **200 items**, one per requested document. Reorder them by dragging or with **Move up** / **Move down**.',
        },
        {
          type: 'table',
          headers: ['Field', 'Meaning'],
          rows: [
            ['Name, Description', 'What the document is called and what it must contain.'],
            ['Required', 'The document is indispensable.'],
            ['Group', 'In the customer portal each group is a tab; items without a group go under "Other documents".'],
            ['Allowed file types', 'pdf, jpg, jpeg, png, webp, heic, docx, odt, txt; at least one is needed.'],
            ['Maximum size', 'Up to 20480 KB (default 10240).'],
            ['Minimum and maximum number of files', 'From 1 to 10; 1 and 1 means a single file.'],
            ['Validation prompt', 'Instruction in Italian for the AI, for example "Check that it is a company registry extract and that it shows the company name".'],
            ['Maximum document age', 'Optional, in days (1-3650).'],
          ],
        },
      ],
    },
    {
      id: 'list',
      title: 'The list',
      blocks: [
        {
          type: 'paragraph',
          text: 'Columns: name, code, active, items, sync, last sync, created on. The sync status can be:',
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
            '**New checklist**: creates a checklist.',
            '**Sync again** (row): resends the checklist to ArchivioSmart.',
            '**Activate** / **Deactivate** (row).',
            '**Delete** (row): the checklist is also removed from ArchivioSmart.',
            '**Sync all**: initial alignment of all checklists.',
          ],
        },
      ],
    },
    {
      id: 'sync-log',
      title: 'Sync details',
      blocks: [
        {
          type: 'paragraph',
          text: "In a checklist's detail, the **Syncs** section shows the last 20 calls to ArchivioSmart with date, operation, outcome, HTTP code and message.",
        },
      ],
    },
    {
      id: 'connection',
      title: 'ArchivioSmart connection',
      blocks: [
        {
          type: 'paragraph',
          text: "Visible only to users with the dedicated permission. You set the API **base address** and the **token**. The token is never shown: leave the field empty to keep it unchanged. **Test connection** checks that the details are correct.",
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
            '**Invalid token**: update the token in the connection.',
            '**Code already used in ArchivioSmart**: choose another code.',
            '**Connection not configured**: set the address and token.',
            '**Too many requests**: the system retries by itself; just wait.',
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
          text: 'There are permissions to **view**, **create**, **edit** and **delete** checklists; managing the connection has a separate permission.',
        },
      ],
    },
  ],
}

export default guide
