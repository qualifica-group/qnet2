import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'custom-fields',
  title: 'Custom Fields',
  summary: "Custom fields add information the modules don't already have, such as a license plate or an expiry date.",
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Administration › Custom Fields**. The field appears in the record and the list of the chosen module.' },
      ],
    },
    {
      id: 'create-custom-field',
      title: 'Creating a custom field',
      blocks: [
        { type: 'steps', items: ['Open **Administration › Custom Fields**.', 'Click **New custom field**.', 'Fill in the sections. The **Preview** box shows in real time how the field will look.', 'Click **Save**.'] },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Module**', 'Where the field will appear, for example Users, Products, Companies or Referents.'],
            ['**Type**', 'The data type (see the Field types section).'],
            ['**Key**', 'Unique internal name, lowercase with underscores, for example contract_expiry.'],
            ['**Label**', 'The name shown on the record and as the column title.'],
            ['**Description**', 'Note shown above the field. Optional.'],
            ['**Help text**', 'Short hint below the field. Optional.'],
            ['**Placeholder**', 'Example text in the empty field, for example DD/MM/YYYY. Optional.'],
            ['**Icon**', 'Icon next to the label. Optional.'],
            ['**Group**', 'Groups several fields under a common title.'],
            ['**Tab**', 'The module tab where the field is shown, if the module uses tabs.'],
            ['**Order**', 'Position among the custom fields: lower numbers come first.'],
            ['**Active**', 'If turned off, the field disappears from records and lists.'],
            ['**Indexed**', 'Makes filters and sorting on this field faster.'],
          ],
        },
      ],
    },
    {
      id: 'field-types',
      title: 'Field types and settings',
      blocks: [
        {
          type: 'table',
          headers: ['Type', 'Typical use'],
          rows: [
            ['**Text**', 'Short line: customer code, license plate, serial number.'],
            ['**Long text**', 'Notes or multi-line descriptions.'],
            ['**Integer**', 'Quantity, number of seats.'],
            ['**Decimal**', 'Price, weight, percentage.'],
            ['**Yes/No**', 'Privacy consent, active yes or no.'],
            ['**Option list**', 'Choice among fixed values, for example low, medium or high.'],
            ['**Relation**', 'Link to records of another module.'],
            ['**Date**, **Date and time**, **Time**', 'Deadlines, appointments, schedules.'],
            ['**Email**, **URL**', 'Email address or website.'],
            ['**Color**', 'A selectable color.'],
            ['**Table**', 'Several rows with their own columns, for example the inspection audits of a work order.'],
          ],
        },
        { type: 'list', items: ['**Option list:** add values with **Add option**. Each option has **Value**, **Label** and, if you want, a color and icon. At least one option is required.', '**Relation:** choose the **Target module** and the **Cardinality**: **Single** links one record, **Multiple** links several.', '**Text** and numbers: you can set lengths, minimum, maximum and decimal digits.'] },
        { type: 'paragraph', text: 'In the **Validation** section, **Required** prevents saving with the field empty. **Unique** prevents two records of the same module from sharing the same value.' },
      ],
    },
    {
      id: 'table-field',
      title: 'Table field type',
      blocks: [
        { type: 'paragraph', text: 'The **Table** type collects several rows of data sharing the same columns, for example audit date, inspector, On/Off Site support and Stage 1/Stage 2 phase. The field form shows the **Columns** editor.' },
        { type: 'steps', items: ['Choose the **Table** type.', 'Use **Add column** to insert columns (up to 20); use the arrows to move them up and down and the bin to remove them.', 'For each column enter **Label**, **Key** (suggested automatically from the label: lowercase letters, numbers and underscores; **id** is reserved), **Type** and whether it is **Required**.', 'Optional: set **Minimum rows** and **Maximum rows** (up to 200).', 'Click **Save**.'] },
        { type: 'list', items: ['**Column types:** Text, Long text, Integer, Decimal, Yes/No, Choice (with Value and Label options), Date, Date and time, Time, Email, URL, Color.', '**Row selection:** turn on the option and enter a label and key: each row gets a selection button and in each record you can select at most one row.', '**Grid summary:** choose the column to show in lists (date, date and time, time, integer, decimal, text or choice) and the strategy: **Selected row** (requires row selection), **Maximum** or **Minimum**. Or no summary.'] },
        { type: 'paragraph', text: 'In lists, if the field has row selection and a row is selected, the cell shows the values of that row inline (dates in the local format, choices with their label, Yes/No with the column name); otherwise it shows the summary followed by the number of rows, for example “12/10/2026 · 3”. With no rows the cell is empty. Hovering the cell with the mouse or moving keyboard focus onto it opens a panel with the full table, with the selected row highlighted. Sorting and text filtering work on the summary. The column cannot be edited in the list: edit it from the record. In document templates the field variable shows the summary.' },
        { type: 'note', text: 'The **Indexed** option is not available for a Table field. If the field is **Required**, at least one row is needed.' },
        { type: 'warning', text: 'You can change the columns even when data already exists: a removed column is no longer shown, while changing a column type does not convert the values already saved.' },
      ],
    },
    {
      id: 'table-field-fill',
      title: 'Filling in a table in a record',
      blocks: [
        { type: 'list', items: ['Click **Add row** and fill in the cells; the remove button deletes the row.', 'If the field has row selection, use the selection button to mark the selected row.', 'Errors appear on the single cell. On smartphones each row is a card.', 'In the detail view the table is read-only and the selected row is highlighted.'] },
      ],
    },
    {
      id: 'display-in-records',
      title: 'How they appear in records',
      blocks: [
        { type: 'paragraph', text: 'Fields with a **Group** appear under the group title; the others under **Other fields**. The order follows the **Order** value.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Editing, deactivating and deleting',
      blocks: [
        { type: 'list', items: ['**Edit:** open the record with **View**, press **Edit**, change the data and click **Save**.', '**Deactivate:** turn off **Active**. The field disappears everywhere, but the values already entered remain.', '**Delete:** choose **Delete** and confirm.'] },
        { type: 'warning', text: "Once a field already holds values, you can no longer change its **Module**, **Type** and **Key**. Deleting a field also deletes all its values: when in doubt, deactivate it instead." },
      ],
    },
  ],
}

export default guide
