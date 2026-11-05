import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'request-imports',
  title: 'Import requests',
  summary:
    'Import requests loads deals already in progress from an Excel or CSV file and creates the requests directly in Request Management: registry record, opportunity and offer.',
  sections: [
    {
      id: 'overview',
      title: 'What it does',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Develop › Import requests**, restricted to users allowed to import requests. Every row of the file becomes a request in **Request Management**: QNet creates the registry record (or reuses the existing one), the opportunity and the offer. **No leads are created** and **no operator is notified** of the assignment.',
        },
        {
          type: 'steps',
          items: ['**Upload**', '**Mapping and configuration**', '**Review**', '**Summary**'],
        },
        {
          type: 'note',
          text: 'One run imports a single **product line**. If the file holds more than one, split it and run one import per line.',
        },
      ],
    },
    {
      id: 'configuration',
      title: 'Configuration',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the mapping step, below the columns, you set the values that apply to the whole file:',
        },
        {
          type: 'list',
          items: [
            '**Product line** (required): the category of the offers created. It decides which workflow statuses are available and which additional information can be mapped. When the file has a category column with a single recognised value, it is already filled in.',
            '**Operational site** (optional): a request normally takes its operator\'s primary site; this one is used only for operators who have none.',
            '**Fallback source** (required): used when a row has no recognized source.',
            '**Fallback operator** (optional): used when the row\'s operator is not recognized; without it, the request is assigned to you.',
          ],
        },
      ],
    },
    {
      id: 'column-mapping',
      title: 'Column mapping and Additional information',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each column pick the destination field, or **Ignore this column**. Besides the registry data you can map **Operator**, **Reporter** (the contact), **Workflow status**, **Source**, **Callback date**, **Product** and **Notes**.',
        },
        {
          type: 'paragraph',
          text: 'Once the product line is chosen, the destination menu also shows **Additional information: <name>** entries, one for each additional information of that category\'s offer (for example the price or the type of subsidy). When a column header matches the name of one of them, QNet suggests it by itself; the others are picked by hand (for example "Data App APL" onto **OK app. APL**).',
        },
        {
          type: 'tip',
          text: 'If you change the product line, columns mapped to information that no longer exists for the new category go back to **Ignore this column**.',
        },
        {
          type: 'note',
          text: 'Ignored or unmappable columns are not lost: when they hold a value, it ends up in the opportunity\'s **General notes** as "Header: value".',
        },
      ],
    },
    {
      id: 'values-panel',
      title: 'The Values panel',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you map **Workflow status** or **Source**, the **Values** panel appears with the distinct values found in the file and the number of rows each one appears in. For each one choose:',
        },
        {
          type: 'list',
          items: [
            'an **existing status** of the product line\'s workflow (or an **existing source**);',
            '**Create new status** (statuses only): enter the name, already filled in with the file value, and the group (**Open**, **Pending**, **Validated**, **Closed won**, **Closed lost**). The status is created in the workflow when the import is confirmed, only once even if you run the import again.',
          ],
        },
        {
          type: 'paragraph',
          text: 'QNet pre-fills the obvious matches. An empty cell is shown as **(empty)** and usually goes to the initial status. Values without a match are highlighted and **you cannot continue** until every value has a choice.',
        },
      ],
    },
    {
      id: 'recognition',
      title: 'What QNet recognizes',
      blocks: [
        {
          type: 'list',
          items: [
            '**Operator**: by first and last name among active users, ignoring case and accents. If the name is ambiguous or unknown the row gets a warning and uses the fallback operator.',
            '**Reporter**: by name among existing contacts. QNet never creates contacts: when the name does not exist, or several contacts share it, the field stays empty, the row gets a warning and the name goes to the general notes.',
            '**Product**: by similarity with the products of the chosen line (acronyms too). If it is not recognized, the offer is created without product lines and the file text goes to the general notes.',
            '**Callback date**: Excel dates and dates written as text are read; the callback is set at **09:00**. An unreadable date stays empty, with a warning.',
            '**Several phone numbers** in one cell (separated by "/" or " - "): become several contacts, the first one is the main one.',
          ],
        },
      ],
    },
    {
      id: 'warnings',
      title: 'Non-blocking warnings',
      blocks: [
        {
          type: 'paragraph',
          text: 'Data QNet cannot use, such as a malformed email, an unreadable date, an unrecognized product or an unexpected value of a choice field, **does not make the row fail**: the row is imported, shows a warning in the review step and the original value is kept in the opportunity\'s **General notes**.',
        },
        {
          type: 'note',
          text: 'QNet reports, it does not fix: cleaning the data in the file is up to you.',
        },
      ],
    },
    {
      id: 'duplicates',
      title: 'Duplicates',
      blocks: [
        {
          type: 'paragraph',
          text: 'Duplicate handling concerns the registry record and works as in **Import leads**:',
        },
        {
          type: 'table',
          headers: ['Option', 'Effect'],
          rows: [
            ['**Always create a new registry record**', 'Every row creates a new registry record.'],
            ['**Use the matching registry record and update its contacts**', 'Opportunity and offer are attached to the existing registry record, without creating a new one.'],
            ['**Skip matching rows**', 'Rows already present write nothing.'],
            ['**Decide in review**', 'Choose row by row whether to skip it or create a new one.'],
          ],
        },
      ],
    },
    {
      id: 'review-and-confirm',
      title: 'Review and confirm',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the review you can fix data in the grid and change **operator**, **site** and **products** per row or in bulk. In the summary you check the totals and confirm: the import continues on the server even if you close the page. A row whose status is not valid for the offer fails and writes nothing; the other rows are imported.',
        },
      ],
    },
    {
      id: 'templates',
      title: 'Mapping templates',
      blocks: [
        {
          type: 'paragraph',
          text: 'By ticking **Save this mapping as a reusable template** you also save the status and source matches you chose on existing items (statuses to be created are not saved). When you load a file with the same columns, QNet recognizes the template, offers to apply it and pre-selects the saved matches in the **Values** panel, without touching the choices you already made by hand.',
        },
      ],
    },
  ],
}

export default guide
