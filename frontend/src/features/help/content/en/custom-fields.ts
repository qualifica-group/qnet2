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
          ],
        },
        { type: 'list', items: ['**Option list:** add values with **Add option**. Each option has **Value**, **Label** and, if you want, a color and icon. At least one option is required.', '**Relation:** choose the **Target module** and the **Cardinality**: **Single** links one record, **Multiple** links several.', '**Text** and numbers: you can set lengths, minimum, maximum and decimal digits.'] },
        { type: 'paragraph', text: 'In the **Validation** section, **Required** prevents saving with the field empty. **Unique** prevents two records of the same module from sharing the same value.' },
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
        { type: 'list', items: ['**Edit:** choose **Edit**, change the data and click **Save**.', '**Deactivate:** turn off **Active**. The field disappears everywhere, but the values already entered remain.', '**Delete:** choose **Delete** and confirm.'] },
        { type: 'warning', text: "Once a field already holds values, you can no longer change its **Module**, **Type** and **Key**. Deleting a field also deletes all its values: when in doubt, deactivate it instead." },
      ],
    },
  ],
}

export default guide
