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
            'Fill in **Name** and, if needed, **Description**; set **Active**.',
            'Save the bundle.',
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
