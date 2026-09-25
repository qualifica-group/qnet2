import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'imports',
  title: 'Import leads',
  summary: 'With Import leads you load many leads from a file; QNet creates the registries or updates the existing ones.',
  sections: [
    {
      id: 'overview',
      title: 'How it works',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page shows the import history (**Date**, **Operator**, **File**, **Records**, **Imported**, **Errors**, **Status**). Click **New import** to start.',
        },
        {
          type: 'steps',
          items: ['**Upload**', '**Mapping**', '**Review**', '**Summary**'],
        },
      ],
    },
    {
      id: 'prepare-the-file',
      title: 'Preparing the file',
      blocks: [
        {
          type: 'list',
          items: [
            '**.csv** or **.xlsx** format, with column names on the first row, no repeated names.',
            'Every row needs at least first and last name, company name, or a valid email or phone number.',
            'To set the campaign row by row, add a column with the campaign code.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Fillable fields: Full name, First name, Last name, Company name, Tax code, VAT number, Email, Phone, Address, ZIP code, Country, Region, Province, City, Notes, Campaign code.',
        },
      ],
    },
    {
      id: 'step-1-upload',
      title: 'Step 1 — Upload',
      blocks: [
        {
          type: 'steps',
          items: [
            'Drag the file into the drop zone, or click to browse for it.',
            'Click **Analyze file** and check **Columns detected**, **Rows detected** and **Duplicate column names**.',
            'Click **Continue to configuration**.',
          ],
        },
      ],
    },
    {
      id: 'step-2-mapping',
      title: 'Step 2 — Mapping',
      blocks: [
        {
          type: 'steps',
          items: [
            'For each **File column**, choose the **Target field**, or **Extra field** or **Ignore this column**.',
            'In **Global configuration**, set **Campaign** (**One for the whole file** or **From the file**), **Source** and **Products of interest**.',
            'In **Duplicate handling**, choose what to do with rows that already exist (see table).',
            'Click **Save mapping and continue**.',
          ],
        },
        {
          type: 'table',
          headers: ['Option', 'Effect'],
          rows: [
            ['**Always create a new lead**', 'Always creates a new record.'],
            ['**Update the matching registry**', 'Updates the existing registry.'],
            ['**Skip matching rows**', 'Does not import rows that already exist.'],
            ['**Decide during review**', 'Decide row by row in the next step.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Duplicates are recognized by email, phone, tax code and VAT number.',
        },
        {
          type: 'tip',
          text: 'Turn on **Save this mapping as a reusable template**. Next time, with a matching file, just click **Apply**.',
        },
      ],
    },
    {
      id: 'step-3-review',
      title: 'Step 3 — Review',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every row has a status: **Valid**, **Warning**, **Error**, **Duplicate** or **Skipped**.',
        },
        {
          type: 'list',
          items: [
            'Fix values directly in the grid: every edit is saved immediately.',
            'For every row you can change the campaign, operator, site, products and location.',
            'For duplicates, choose the **Resolution**: **Skip**, **Create new** or **Update existing**.',
            'On several selected rows, use **Assign operators** (with **Balanced split** the per-Site operator list appears, all selected: deselect whoever should not receive rows) or **Assign products**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Then click **Continue to summary**.',
        },
      ],
    },
    {
      id: 'step-4-summary',
      title: 'Step 4 — Summary and confirmation',
      blocks: [
        {
          type: 'paragraph',
          text: 'The summary shows totals, **Selected values**, **Duplicate resolution**, **Mapped columns**, **Extra fields** and **Warnings**. **Automatically convert to Opportunity** is on by default.',
        },
        {
          type: 'paragraph',
          text: 'If the import is not ready (rows with no operator or site, campaign with no product line), QNet says so: use **Back to review**. Otherwise click **Confirm and import**.',
        },
      ],
    },
    {
      id: 'results-and-errors',
      title: 'Results and errors',
      blocks: [
        {
          type: 'paragraph',
          text: "The import keeps running in the background: you can close the page and you will get a notification. From the history, click **View** on an import to see **Statistics**, **Metadata**, **Errors** (with **Download the error report**) and the imported **Records**.",
        },
        {
          type: 'tip',
          text: 'If an import was not completed, open it and click **Resume import**.',
        },
      ],
    },
  ],
}

export default guide
