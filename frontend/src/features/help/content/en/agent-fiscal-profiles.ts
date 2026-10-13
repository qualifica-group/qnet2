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
          text: 'Use **New fiscal profile** to create one; from a row you can open, edit or delete it. In the current version the profile is only recorded: it is **not used yet** to compute settlements, so deleting it has no consequences.',
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
            ['**Notes**', 'Free text, up to 2000 characters.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'ENASARCO rates and ceilings are **not** entered in the profile: they will arrive with the settlement calculation.',
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
