import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-bundles',
  title: 'Document bundles',
  summary: 'Reusable file sets to attach to work order emails.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is found in **Configuration › Document bundles**. A document bundle is a set of files (for example a delivery form or a privacy notice) that a work order\'s email composer can attach all at once with **From document bundle**.',
        },
        {
          type: 'note',
          text: 'Only **Active** bundles show up in the composer picker; a deactivated bundle can still be edited and deleted from here, together with all its files.',
        },
      ],
    },
    {
      id: 'create-bundle',
      title: 'Creating a bundle and uploading files',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Configuration › Document bundles** and press **New document bundle**.',
            'The sheet opens with closed rows: for **Name**, **Description** and **Active** press the pencil (or the value), fill in and press **Done**; **Revert** puts the row back as it was.',
            'Press **Save**: the bundle is created with all the rows at once.',
            "On the bundle's detail, in the **Files** tab, drag one or more files onto the upload area or click it to pick them.",
          ],
        },
        {
          type: 'tip',
          text: 'The number of uploaded files shows in the table\'s **Files** column; remove a file from the detail with the trash icon on its row.',
        },
      ],
    },
    {
      id: 'edit-bundle',
      title: 'Editing a bundle',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit form: open the bundle with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was. One row is open at a time.',
        },
      ],
    },
    {
      id: 'usage',
      title: 'Where it is used',
      blocks: [
        {
          type: 'paragraph',
          text: "An **Active** bundle is one of the attachment sources of a work order's email composer.",
        },
      ],
    },
  ],
}

export default guide
