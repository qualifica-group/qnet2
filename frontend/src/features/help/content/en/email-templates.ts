import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'email-templates',
  title: 'Email templates',
  summary: 'Reusable templates with placeholders to compose work order and invoice emails.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: "The module is found in **Configuration › Email templates**. A template is the **Subject**/**Body** pair the work order's email composer offers when you pick **Email template**: the available **Modules** are **Work orders** and **Invoices**.",
        },
        {
          type: 'note',
          text: 'Only **Active** templates show up in the composer picker; a deactivated template can still be edited and deleted from here.',
        },
      ],
    },
    {
      id: 'create-template',
      title: 'Creating a template',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Configuration › Email templates** and press **New email template**.',
            'Fill in **Name**, **Module** and **Subject**.',
            "Write the **Body** with the text editor: images are not allowed in an email body.",
            'If needed, add a **Description** for internal use and set **Active**.',
            'Save the template.',
          ],
        },
        { type: 'warning', text: 'After creation, **Module** can no longer be changed.' },
      ],
    },
    {
      id: 'placeholders',
      title: 'Placeholders',
      blocks: [
        {
          type: 'paragraph',
          text: 'A placeholder is text in curly braces, for example **{work_order.code}**, that gets replaced with the work order\'s real data the moment you pick the template in the composer. To insert one, open the **Insert placeholder** panel next to the Subject or above the Body, search for what you need and click it to add it right where the cursor is.',
        },
        {
          type: 'list',
          items: [
            '**Work order** (code, title, type, dates, description) and **Sender** (name and email of who sends it).',
            '**Quote**, **Client**, **Opportunity**, **Referent**, **Sales rep**, **Reporter**, **Supervisor**, **Company** and **Company site**: the same data from the linked quote already used in document layouts.',
          ],
        },
        {
          type: 'paragraph',
          text: 'The **Invoices** module offers the **Invoice**, **Customer**, **Company**, **Payment**, **Totals** and **Sender** placeholders, plus the **Reminder** ones: the **overdue due dates list** and the **overdue amount**.',
        },
        {
          type: 'note',
          text: "Placeholders are resolved ONLY ONCE, when you pick the template in the composer: subject and body stay freely editable afterwards and are never recalculated when sending. An unknown placeholder, or one with no data to show, simply comes out empty.",
        },
      ],
    },
    {
      id: 'usage',
      title: 'Where it is used',
      blocks: [
        {
          type: 'paragraph',
          text: "An **Active** template is selectable from a work order's email composer (Work orders templates) or an active invoice's, including the **Reminder** (Invoices templates).",
        },
      ],
    },
  ],
}

export default guide
