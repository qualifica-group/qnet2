import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'stock',
  title: 'Equipment',
  summary: 'Where the physical goods for internal use (laptops, phones, furniture) are and who they are assigned to, with the history of every movement.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Equipment** page keeps the stock of physical goods for internal use: for each product, the quantity in every operational site, either free or assigned to a person. Each combination of product, site and person is a row with its own quantity. It has two tabs, **Stock** and **Movements**.',
        },
        {
          type: 'warning',
          text: 'The module is only visible to people with the **View list** permission on Equipment; anyone without it sees neither the menu entry nor the Stock and Equipment sections on the product and user records.',
        },
      ],
    },
    {
      id: 'tracked-products',
      title: 'Which products can be moved',
      blocks: [
        {
          type: 'paragraph',
          text: 'Only **tracked** products: of type **Physical good** and with the **Internal use** box ticked (in the Products module). **Internal use** can only be chosen on a Physical good, otherwise the form shows an error on the field.',
        },
        {
          type: 'note',
          text: 'While a product has stock greater than zero you cannot untick Internal use or change its type. A Physical good can still be used in offers only if it is also Sellable or Usable as cost.',
        },
      ],
    },
    {
      id: 'balances',
      title: 'Stock tab',
      blocks: [
        {
          type: 'paragraph',
          text: 'The rows with a quantity greater than zero, with product, site, person (empty when the goods are free), **Free** or **Assigned** status, quantity, unit of measure and the date of the last movement. With the **Manage** permission the row actions are **Assign** (free rows only), **Return** (assigned rows only), **Transfer**, **Unload** and **Adjust**. **History** is always available and shows the movements of that row, newest first.',
        },
      ],
    },
    {
      id: 'movements',
      title: 'Movement types',
      blocks: [
        {
          type: 'table',
          headers: ['Movement', 'What it does'],
          rows: [
            ['Load', 'Adds free goods to a site.'],
            ['Unload', 'Removes goods from a site (for example because they are disposed of). The note is required.'],
            ['Transfer', 'Moves free goods, or a person’s equipment, to another active site.'],
            ['Assignment', 'Assigns free goods of a site to an active person: the equipment stays tied to that site.'],
            ['Return', 'Puts a person’s equipment back among the free goods of the same site.'],
            ['Adjustment', 'Aligns the stock to a counted quantity. The note is required; zero deletes the row.'],
          ],
        },
      ],
    },
    {
      id: 'record-movement',
      title: 'Recording a movement',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Load** or **Transfer** at the top right, or an action on a row (the form opens already filled in).',
            'In the form the fields change with the type: for example **Recipient** for an assignment, **Destination site** for a transfer, **Counted quantity** for an adjustment.',
            'Enter the quantity and, if needed, the date (it cannot be in the future) and the note, then press **Record movement**.',
          ],
        },
        {
          type: 'note',
          text: 'You cannot unload, transfer or assign more than the quantity available in the source row: the form shows the available quantity. Movements can be neither edited nor deleted: to fix a mistake record an opposite movement or an adjustment.',
        },
      ],
    },
    {
      id: 'product-and-user',
      title: 'On the product and user records',
      blocks: [
        {
          type: 'paragraph',
          text: 'The record of a tracked product shows the **Stock** section (site, person or “In site”, quantity) and the Products grid the **Stock** column. A user’s record shows the **Equipment** section with what they hold; with the **Manage** permission each row has **Return**, which records, after confirmation, the return of the whole quantity.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Who sees and who writes',
      blocks: [
        {
          type: 'table',
          headers: ['Permission', 'What it allows'],
          rows: [
            ['View list', 'Opens the page, the stock, the movements and the history; shows the sections on the product and user records.'],
            ['Manage', 'Records movements (includes reading).'],
          ],
        },
      ],
    },
  ],
}

export default guide
