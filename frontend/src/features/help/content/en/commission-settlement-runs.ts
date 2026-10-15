import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlement-runs',
  title: 'Runs',
  summary: 'The monthly settlement run: creates a draft statement for each agent and company.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Accounting › Agent commissions › Runs**. A **run** takes the open accruals of your agents and, for each **agent + issuing company** pair, creates a **draft statement** with taxable amount, VAT, withholding, ENASARCO and net payable. The list shows past runs, read-only; **View** opens the run in a side sheet: period, threshold, who ran it and when, then the statements created and the agents skipped.',
        },
      ],
    },
    {
      id: 'new-run',
      title: 'How to run a settlement',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New run**: a side sheet opens with its rows closed. Open a row with the pencil, change it and confirm with **Done** (or **Revert**).',
            'Set the period. **Period to** is the date that matters: **every** open movement accrued up to that day enters, including those of earlier periods left pending. **Period from** is informative only. The previous month is proposed by default.',
            'Optionally narrow with **Company**, **Agents** and **Roles** (Commercial, Reporter, Supervisor). Leaving them empty considers everything.',
            'Optionally set a **minimum threshold**: statements below that amount are not created. If left empty the system default applies.',
            'Press **Run**: the run is immediate and the sheet then shows the run just created, with the statements created and the agents skipped. Leaving before running, after changing something, asks for confirmation.',
          ],
        },
      ],
    },
    {
      id: 'outcome',
      title: 'The outcome: created and skipped',
      blocks: [
        {
          type: 'paragraph',
          text: 'The outcome lists the **statements created**, with a link to open each draft, and the **agents skipped** with the reason. A skipped agent **loses nothing**: their movements stay open and are picked up by the next run.',
        },
        {
          type: 'table',
          headers: ['Reason', 'What it means'],
          rows: [
            ['**A draft already exists**', 'There is already a draft for that agent and company: recalculate or delete it from the Statements page.'],
            ['**Non-positive balance**', 'The total (accruals plus adjustments) is zero or negative, for instance because of a reversal: the balance is **carried over** to the next run.'],
            ['**Below the minimum threshold**', 'The taxable amount is under the threshold: the accrual stays open and adds to the following month.'],
            ['**No fiscal profile**', 'The agent has no valid fiscal profile at the period end date: create it in Fiscal profiles.'],
            ['**ENASARCO parameters missing**', 'The agent is subject to ENASARCO but the year row is missing in ENASARCO parameters.'],
          ],
        },
        {
          type: 'tip',
          text: 'Internal users (employees) are never settled from here: only agents, that is referents.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module under development',
      blocks: [
        {
          type: 'note',
          text: 'Module under development. The guide will be completed when the module is complete.',
        },
      ],
    },
  ],
}

export default guide
