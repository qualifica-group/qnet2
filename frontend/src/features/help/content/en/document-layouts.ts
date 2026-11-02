import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-layouts',
  title: 'Layouts',
  summary: "A layout is the graphic template qnet uses to generate a quote's or an invoice's document.",
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Documents › Layouts**. The available **Modules** are **Quotes** (the document is downloaded with **Download quote** on the quote) and **Invoices** (downloaded with **Download PDF** in Active invoices) and **ODA** (printed with **Print PDF** on the purchase order page).' },
      ],
    },
    {
      id: 'create-layout',
      title: 'Creating a layout',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Documents › Layouts** and press **New layout**.', 'The sheet opens with closed rows: for **Name**, **Code**, **Module**, **Description**, **Active** and **Default** press the pencil (or the value), fill in and press **Done**; **Revert** puts the row back as it was.', 'The **Content** section is already open: build the page with the visual editor.', 'Press **Save**: the layout is created with everything at once.'] },
        { type: 'warning', text: 'After creation, **Code** and **Module** cannot be changed.' },
      ],
    },
    {
      id: 'edit-layout',
      title: 'Editing a layout',
      blocks: [
        { type: 'paragraph', text: 'There is no separate edit form: open the layout with **View**, press the pencil on the row (**Name**, **Description**, **Active**, **Default**), change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was. **Content** is a full-width row showing the page preview: its pencil opens the same visual editor as creation and **Save** stores the content alone.' },
      ],
    },
    {
      id: 'clone-layout',
      title: 'Cloning a layout',
      blocks: [
        { type: 'steps', items: ['Open the layout to start from and press **Clone** at the top right (or pick **Clone** among the row actions in the list).', 'In the **Clone layout** window check the copy’s **Name** and **Code**: they are suggested as “name (copy)” and “code_copy”, and you can change them.', 'Press **Clone**: the new layout opens and you can keep editing it.'] },
        { type: 'paragraph', text: 'The copy keeps the same **Module**, **Description**, **Active** status and the whole **Content**, images (logo, letterhead) and page settings included. The original layout stays as it was.' },
        { type: 'note', text: 'The copy is never the **Default**: set it afterwards if needed. Creating layouts requires the matching permission.' },
      ],
    },
    {
      id: 'editor',
      title: 'The visual editor',
      blocks: [
        { type: 'paragraph', text: 'The editor has three zones: **Header**, **Body** and **Footer**. In each you add blocks with **Add block** and reorder them by dragging.' },
        {
          type: 'table',
          headers: ['Block', 'Use'],
          rows: [
            ['**Text**', 'Paragraphs with style, font, color and alignment; here you find **Insert page number** and **Insert page total**.'],
            ['**Image**', 'For example a logo, **Inline** or **Behind the page** as a background. To upload it, save the layout first.'],
            ['**Table**', 'A free-form table, with rows, columns and borders.'],
            ['**Product table**', 'For **Quotes** it lists **Quote lines** or **Cost lines**; for **Invoices** the **Document lines** or the **Due dates**; for **ODA** the **Purchase order lines** (Description, Cost type, Unit of measure, Quantity, Unit price, VAT rate, Taxable amount, VAT, Total). You choose the columns (Code, Name, Quantity, Unit price, VAT rate, amounts) and the total rows.'],
            ['**Page break**, **Spacer**, **Divider**', 'Layout and pagination.'],
          ],
        },
        { type: 'note', text: 'The **Page** panel sets orientation (Portrait or Landscape), margins and default font.' },
        { type: 'paragraph', text: 'The preview shows the document over several A4 sheets: when the **Body** no longer fits in the space between header and footer (margins included), the next block moves to the following sheet, as it does after a **Page break**. Each sheet repeats the **Behind the page** image, the **Header** and the **Footer**, with its own page number. A block taller than a whole sheet stays clipped in the preview; in the downloaded document it continues on the next sheet.' },
      ],
    },
    {
      id: 'variables',
      title: 'Variables',
      blocks: [
        { type: 'paragraph', text: "Variables are placeholders that, when the document is generated, become the quote's actual data. Select some text in the preview and, in the **Variables** panel, click the piece of data you need." },
        { type: 'paragraph', text: 'Available groups: **Quote**, **Totals**, **Customer**, **Opportunity**, **Referent**, **Salesperson**, **Referrer**, **Supervisor**, **Company**, **Site** (with bank and IBAN), **Operational site**, **Custom fields** and **Quote attributes**. The last two update on their own when you add fields or attributes.' },
        { type: 'paragraph', text: 'A choice attribute prints its options\' **labels**, not their codes; for a multiple choice, the labels are separated by commas.' },
      ],
    },
    {
      id: 'invoices-module',
      title: 'The Invoices module',
      blocks: [
        { type: 'paragraph', text: 'When you choose the **Invoices** **Module** on creation, the new layout starts **prefilled** with a starter structure: company, customer, title, document lines table with totals, collected and outstanding, due dates table, payment with bank and IBAN, notes. You can edit it freely.' },
        { type: 'paragraph', text: 'Available variables: **Document**, **Customer**, **Company**, **Site**, **Payment and bank**, **Totals**, **Work order** and **Quote**.' },
        { type: 'note', text: 'Invoices have no automatic site logo yet: upload the logo as an **Image** of the layout.' },
      ],
    },
    {
      id: 'default-and-deletion',
      title: 'Default layout and deletion',
      blocks: [
        { type: 'paragraph', text: "The **Default** layout is used when the quote doesn't specify another one. A quote keeps the layout it was created with." },
        { type: 'note', text: 'The default layout must be active and cannot be deactivated or deleted until you designate another one.' },
        { type: 'warning', text: 'A layout used by quotes cannot be deleted, only deactivated.' },
      ],
    },
  ],
}

export default guide
