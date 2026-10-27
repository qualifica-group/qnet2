import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-agents',
  title: 'Agents',
  summary: 'For each agent, the commissions accrued in the period, those still to settle, in a statement, approved and unpaid, and what has been paid.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Agents** page answers “how much have we accrued with each agent, how much is left to settle and how much have we already paid?”. You find it under **Accounting › Management control › Agents**. It is **read-only** and shows aggregate amounts; for the single movements use **Agent commissions › Accruals** and **Statements**.',
        },
        {
          type: 'paragraph',
          text: 'At the top are 6 tiles: **Accrued**, **To settle**, **In statement** (with the number of statements still **awaiting the document** of the agent), **Approved, unpaid**, **Paid** and the number of **Agents**. Below, the table by agent and the charts.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'By default the period runs from **1 January to today**; it can span at most **24 months**. You can narrow by **company** and choose how many agents to show (**top 10, 20 or 50**): the others are summed in an **Others** row. The period applies to **accrued** (accrual date) and **paid** (payment date of the installments); **to settle**, **in statement** and **approved, unpaid** are always a snapshot **as of today**.',
        },
        {
          type: 'note',
          text: 'Accrued amounts are taxable amounts, as in the commissions; statements and payments are the recorded amounts (net payable and amount paid).',
        },
      ],
    },
    {
      id: 'agents',
      title: 'Agents',
      blocks: [
        {
          type: 'list',
          items: [
            '**Accrued**: signed sum of the commissions accrued in the period. Reversals reduce it.',
            '**To settle**: accrued commissions not yet included in a statement.',
            '**In statement**: net payable of the statements issued or with the document received. The **awaiting document** count is the issued statements for which the agent has not yet sent the document.',
            '**Approved, unpaid**: net payable of the approved statements, minus what their purchase invoice has already paid.',
            '**Paid**: what was paid in the period on the installments of the purchase invoice linked to the statement. Cancelled statements do not count.',
          ],
        },
        {
          type: 'paragraph',
          text: 'The table sorts the agents by amount **to settle** (ties by accrued), and closes with the **Others** row (when there are more agents) and the **Total**. Agents are the contacts that receive the commissions, whatever their role.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Trend by month',
      blocks: [
        {
          type: 'paragraph',
          text: 'Two charts set **accrued and paid** side by side: one by agent, the other by month, including months with no movements.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Management control** permission is required (from the Roles form), the same as the other pages of the module. Whoever has it sees the data of **all** companies. Without the permission the menu entry does not appear.',
        },
      ],
    },
  ],
}

export default guide
