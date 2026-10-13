import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enasarco-parameters',
  title: 'ENASARCO parameters',
  summary: 'Yearly rates and ceilings used to compute ENASARCO on the statements.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Accounting › Agent commissions › ENASARCO parameters**. It holds **one row per year**: a statement uses the parameters of the year of the period **end date**. You enter the values yourself: the system contains no preset ones.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'Meaning'],
          rows: [
            ['**Year**', 'From 2000 to 2100, only one per year: an existing year raises an error on the field.'],
            ['**Total rate (%)**', 'Contribution percentage applied to the base, from 0 to 100 with 2 decimals.'],
            ['**Agent share (%)**', 'Part of the contribution withheld from the agent. The rest is borne by the principal and is informative only.'],
            ['**Single-firm / multi-firm ceiling**', 'Yearly amount beyond which no more contribution is computed. The one matching the mandate type of the fiscal profile is used.'],
            ['**Notes**', 'Free text, up to 2000 characters.'],
          ],
        },
        {
          type: 'warning',
          text: 'If an agent has ENASARCO active and the year row is missing, the run **skips** that agent (reason: parameters missing). Enter the year and run again.',
        },
        {
          type: 'paragraph',
          text: 'A year already used by an **issued** statement cannot be deleted.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module under development',
      blocks: [
        {
          type: 'note',
          text: 'Module under development. Minimum contribution, FIRR and quarterly payments are not handled yet.',
        },
      ],
    },
  ],
}

export default guide
