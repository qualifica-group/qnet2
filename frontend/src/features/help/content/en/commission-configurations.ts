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
          'In **Recipient and scope**: type the **Configuration name**; choose the **Recipient role** (Salesperson, Referrer, Supervisor or Supplier); choose the **Application scope** (Product category, Product or Specific recipient).',
          'Depending on the scope, indicate **Product category**, **Product** or **Recipient type** (Referent, User or Registry) and **Recipient**.',
          'In **Calculation** choose the **Commission type** (Fixed amount or Percentage), the **Commission value**, the **Rule priority** and the **Status** (Active or Suspended).',
          'In **Validity and notes** indicate the **Validity start date** (required) and the optional **Validity end date**.',
          'If you want, add an **Internal service note** and press **Save**. While creating, the form is identical to the detail: each row opens with the pencil and is confirmed with **Done** (**Revert** undoes the row); leaving before saving asks for confirmation.',
        ] },
        { type: 'tip', text: 'The rules of a single registry (supplier or not) can also be viewed and created from its detail, in the **Configured commissions** tab: there the form already starts with Role **Supplier** and that registry as Recipient.' },
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
          'A rule on a Category also applies to all its subcategories, and it can sit on a parent category too (for example ISO). When several category rules cover the product, the one on the category closest to the product wins.',
          'If several rules remain on the same category or product, the highest **Rule priority** wins; if tied, the most recent start date.',
        ] },
      ],
    },
    {
      id: 'manage',
      title: 'Editing, suspending and deleting',
      blocks: [
        { type: 'paragraph', text: 'From the list you can use **View** and **Delete** on the row, if your role allows it. There is no edit page: open the record with **View** and change one value at a time, with the pencil next to it (or by clicking the value), then **Save** to confirm or **Cancel** to go back. Data that must stay consistent is edited together in a single row: **Recipient** (role, recipient type and recipient), **Scope** (application scope and category or product) and **Validity** (start and end date).' },
        { type: 'warning', text: 'A configuration in use cannot be deleted: set it to **Suspended** instead.' },
      ],
    },
  ],
}

export default guide
