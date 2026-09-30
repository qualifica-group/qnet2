import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'attributes',
  title: 'Attributes',
  summary: 'Attributes are reusable extra fields you assign to product categories.',
  sections: [
    {
      id: 'overview',
      title: 'What attributes are',
      blocks: [
        {
          type: 'paragraph',
          text: 'Attributes are reusable extra fields (colour, power, duration) assigned to product categories. Once created, the same attribute can be used by several categories.',
        },
      ],
    },
    {
      id: 'field-types',
      title: 'Field types',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each attribute you enter a Code, a Name and the field type:',
        },
        {
          type: 'list',
          items: [
            'Text',
            'Long text',
            'Integer number',
            'Decimal number',
            'Yes/No',
            'Option list',
            'Relation',
            'Date',
            'Date and time',
            'Time',
            'Email',
            'URL',
            'Colour',
            'Table',
          ],
        },
        {
          type: 'note',
          text: 'An Option list requires at least one option, with all values different; a Relation requires the linked module.',
        },
      ],
    },
    {
      id: 'table-field',
      title: 'Table attribute',
      blocks: [
        { type: 'paragraph', text: 'The Table type collects several rows sharing the same columns, for example the inspection audits of a work order (audit date, inspector, On/Off Site support, Stage 1/Stage 2 phase). The attribute form shows the Columns editor.' },
        {
          type: 'list',
          items: [
            'Add columns (up to 20), move them up and down or remove them. For each one enter Label, Key (suggested from the label: lowercase letters, numbers and underscores; id is reserved), Type and whether it is Required.',
            'Column types: Text, Long text, Integer, Decimal, Yes/No, Choice (with Value and Label options), Date, Date and time, Time, Email, URL, Colour.',
            'Row selection: adds a selection column; in each record at most one row can be selected.',
            'Grid summary: the column to show in lists and the strategy (Selected row, Maximum, Minimum), or no summary. Selected row requires row selection.',
            'Minimum and maximum rows (up to 200).',
          ],
        },
        { type: 'paragraph', text: 'In the record form (for example the work order) the table is filled in with Add row; on smartphones each row is a card. If the attribute is required at least one row is needed; errors appear on the single cell. In lists, if row selection is on and a row is selected, the cell shows the values of that row inline (dates in the local format, choices with their label, Yes/No with the column name); otherwise it shows the summary and the number of rows (for example "12/10/2026 · 3"). With no rows the cell is empty. Hovering the cell or moving keyboard focus onto it opens a panel with the full table, with the selected row highlighted. The column cannot be edited in the grid. In the detail view the table is read-only, with the selected row highlighted.' },
        { type: 'note', text: 'Columns can be changed even when data already exists: a removed column is no longer shown, while changing a column type does not convert the values already saved.' },
      ],
    },
    {
      id: 'creating-an-attribute',
      title: 'Creating an attribute',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open Products › Attributes and press New attribute.',
            'Enter Code and Name.',
            'Choose the field Type.',
            'For an Option list add at least one option, with a Value and a Label.',
            'For a Relation choose the linked module.',
            'Press Save.',
          ],
        },
      ],
    },
    {
      id: 'duplicate-attribute',
      title: 'Duplicating an attribute',
      blocks: [
        {
          type: 'steps',
          items: [
            'On the row of the attribute to copy choose Duplicate (you need the permission to create attributes).',
            'The create form opens pre-filled with the attribute\'s data: the Code gets the "_copy" suffix, the Name the " (copy)" suffix; type, options, configuration and other fields are the same.',
            'Change what you need (the Code must stay unique) and press Save.',
          ],
        },
        {
          type: 'list',
          items: [
            'The copy is a new attribute: it is not assigned to any category and has no values on products.',
            'The original attribute does not change.',
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'The field type cannot be changed after creation. An attribute assigned to a category or with values on products cannot be deleted.',
        },
        {
          type: 'note',
          text: 'To decide where an attribute appears (Product, Offer or Work Order) go to the product category: see the Product Categories guide.',
        },
      ],
    },
  ],
}

export default guide
