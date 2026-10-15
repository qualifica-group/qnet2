import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'agent-fiscal-profiles',
  title: 'Fiscal profiles',
  summary: 'Withholding tax and ENASARCO of each agent, with the period they apply to.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Accounting › Agent commissions › Fiscal profiles**. For each agent (a **referent**) it holds the tax settings used to settle commissions: **withholding tax** and **ENASARCO**.',
        },
        {
          type: 'paragraph',
          text: 'Use **New fiscal profile** to create one; from a row you can open or delete it; editing happens on the record or directly in the list cell. The profile valid at the **end date of a run period** is the one used to compute the agent statement; without a valid profile the agent is skipped.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Profile fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'Meaning'],
          rows: [
            ['**Agent**', 'The referent the profile belongs to.'],
            ['**Valid from / Valid until**', 'The validity period. When **Valid until** is empty the profile is open, that is it has no end date.'],
            ['**Apply withholding tax**', 'When on, **Withholding rate (%)** and **Taxable base (%)** appear; both are required, from 0 to 100 with at most 2 decimals. When off the two fields are hidden and not saved.'],
            ['**Apply ENASARCO**', 'When on, **Mandate type** appears and is required: **Single-firm** or **Multi-firm**. When off the field is hidden.'],
            ['**VAT rate**', 'The VAT added to the agent statements. If left empty the transaction has **no VAT** (for instance an agent under the flat-rate regime).'],
            ['**Payment method**', 'Optional. It is the method suggested when you approve a statement of the agent, to generate the installments of the passive invoice.'],
            ['**Cost type**', 'Optional. It is the cost type suggested for the line of the passive invoice created by the approval.'],
            ['**Notes**', 'Free text, up to 2000 characters.'],
          ],
        },
        {
          type: 'paragraph',
          text: '**VAT rate**, **Payment method** and **Cost type** are the values suggested in the statement approval window, where they stay required and editable. ENASARCO rates and ceilings are **not** entered in the profile: they live in the **ENASARCO parameters** page, one per year.',
        },
      ],
    },
    {
      id: 'validity',
      title: 'Validity periods',
      blocks: [
        {
          type: 'paragraph',
          text: 'For the same agent periods **cannot overlap**, not even when one is open. If you try to save a period that overlaps another, the **Valid from** field shows the error and the profile is not saved: close the previous profile first by setting its **Valid until**.',
        },
        {
          type: 'tip',
          text: 'The **Current** column tells which profiles are valid today.',
        },
      ],
    },
    {
      id: 'edit',
      title: 'Editing a profile',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no edit page: on the profile record hover the value and click the **pencil** (or the value itself), change it and confirm with **Save**; **Cancel** (or Esc) restores the value without saving. One row opens at a time and each save changes that row only.',
        },
        {
          type: 'paragraph',
          text: 'The data the system checks together share one row: **Validity** (Valid from and Valid until), **Withholding** (switch, rate and taxable base) and **ENASARCO** (switch and mandate type). If a save is refused, the reason shows on the open row.',
        },
        {
          type: 'paragraph',
          text: 'In the list you can edit **Valid from**, **Valid until**, **VAT rate** directly in the cell and, only on profiles with the matching switch on, **Withholding rate**, **Taxable base** and **Mandate type**. Agent and switches are changed on the record.',
        },
        {
          type: 'paragraph',
          text: 'Creating a profile looks like the record too: open the rows one at a time, confirm each with **Done** (or **Revert** to go back) and finally press **Save**. Leaving without saving asks for confirmation.',
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
