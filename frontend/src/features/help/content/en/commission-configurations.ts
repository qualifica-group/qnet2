import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-configurations',
  title: 'Commission Configurator',
  summary: 'Holds the rules that automatically calculate commissions on quote lines: who receives it, on which products and how much it is worth.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Commission Configurator**. In the recommended order, category rules are prepared right away, product rules after loading the products.' },
      ],
    },
    {
      id: 'create-configuration',
      title: 'Creating a configuration',
      blocks: [
        { type: 'steps', items: [
          'Open **Configuration › Commission Configurator** and press **New configuration**.',
          'In **Identity and scope**: type the **Configuration name**; choose the **Recipient role** (Salesperson, Referrer, Supervisor or Supplier); choose the **Application scope** (Product category, Product or Specific recipient).',
          'Depending on the scope, indicate **Product category**, **Product** or **Recipient type** (Referent, User or Registry) and **Recipient**.',
          'In **Calculation** choose the **Commission type** (Fixed amount or Percentage), the **Commission value** and the **Rule priority**.',
          'In **Validity** indicate the **Validity start date** (required), the optional **Validity end date** and the **Status** (Active or Suspended).',
          'If you want, add an **Internal service note** and press **Save**.',
        ] },
      ],
    },
    {
      id: 'rule-selection',
      title: 'How the rule is chosen',
      blocks: [
        { type: 'list', items: [
          'Only **Active** rules valid at the reference date apply.',
          'The recipient is not chosen on the line: Salesperson, Referrer and Supervisor are those of the quote, the Supplier is that of the product.',
          'Rules for a specific recipient win over rules valid for the whole role.',
          'All else equal, a rule on a Product wins over a rule on a Category.',
          'If several rules remain, the highest **Rule priority** wins; if tied, the most recent start date.',
        ] },
      ],
    },
    {
      id: 'manage',
      title: 'Editing, suspending and deleting',
      blocks: [
        { type: 'paragraph', text: 'From the list you can use **View**, **Edit** and **Delete** on the row, if your role allows it.' },
        { type: 'warning', text: 'A configuration in use cannot be deleted: set it to **Suspended** instead.' },
      ],
    },
  ],
}

export default guide
