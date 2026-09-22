import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-layouts',
  title: 'Layouts',
  summary: "A layout is the graphic template qnet uses to generate a quote's document.",
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Layouts**. Today the only available **Module** is **Quotes**; the document is downloaded with **Download quote** on the quote.' },
      ],
    },
    {
      id: 'create-layout',
      title: 'Creating a layout',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Layouts** and press **New layout**.', 'In the **Details** tab fill in **Name**, **Code**, **Module** and **Description**; set **Active** and, if needed, **Default**.', 'Save the layout.', 'Open the **Content** tab and build the page with the visual editor.'] },
        { type: 'warning', text: 'After creation, **Code** and **Module** cannot be changed.' },
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
            ['**Product table**', 'Lists **Quote lines** or **Cost lines**, with the chosen columns (Code, Name, Quantity, Unit price, VAT rate, amounts) and the total rows.'],
            ['**Page break**, **Spacer**, **Divider**', 'Layout and pagination.'],
          ],
        },
        { type: 'note', text: 'The **Page** panel sets orientation (Portrait or Landscape), margins and default font.' },
      ],
    },
    {
      id: 'variables',
      title: 'Variables',
      blocks: [
        { type: 'paragraph', text: "Variables are placeholders that, when the document is generated, become the quote's actual data. Select some text in the preview and, in the **Variables** panel, click the piece of data you need." },
        { type: 'paragraph', text: 'Available groups: **Quote**, **Totals**, **Customer**, **Opportunity**, **Referent**, **Salesperson**, **Referrer**, **Supervisor**, **Company**, **Site** (with bank and IBAN), **Operational site**, **Custom fields** and **Quote attributes**. The last two update on their own when you add fields or attributes.' },
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
