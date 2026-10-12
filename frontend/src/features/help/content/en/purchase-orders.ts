import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-orders',
  title: 'ODA',
  summary: 'Purchase orders to suppliers, with lines, cost type and printing.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Purchasing › ODA** and lists the purchase orders to suppliers. The **status tabs** at the top narrow the list; the arrow on the left of each row shows the order lines.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module under development',
      blocks: [
        {
          type: 'note',
          text: 'Module under development. The guide will be published when the module is complete.',
        },
      ],
    },
  ],
}

export default guide
