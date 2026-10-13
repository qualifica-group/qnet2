import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlements',
  title: 'Statements',
  summary: 'The commission settlement statements: drafts to review, issued and cancelled.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Accounting › Agent commissions › Statements**. A **statement** gathers the commission movements of one agent for one company and computes what must be paid. They are born as a **draft** from a run.',
        },
        {
          type: 'paragraph',
          text: 'The **tabs** above filter by state: **Drafts** (default), **Issued**, **Cancelled** and **All**. Below the table are the **totals** of the current filter; you can group by state, agent, company and month. Open the detail from the row.',
        },
      ],
    },
    {
      id: 'amounts',
      title: 'How the amounts are computed',
      blocks: [
        {
          type: 'list',
          items: [
            '**Taxable amount** = accruals (commissions minus reversals) + adjustments.',
            '**VAT** = taxable amount × the VAT rate of the fiscal profile. With no rate on the profile the transaction has no VAT and VAT is zero.',
            '**Withholding tax** = taxable amount × taxable base (%) × withholding rate (%), when withholding is active on the profile.',
            '**ENASARCO** is computed on a base that does not exceed the yearly **ceiling**. The ceiling (single-firm or multi-firm) comes from the ENASARCO parameters of the period year, minus what is already covered by the **issued** statements of the same agent and company in the same year. The total rate is applied to the base; the agent share is **withheld**, the principal share is **informative only** (it is a company cost).',
            '**Net payable** = taxable amount + VAT − withholding − ENASARCO borne by the agent.',
          ],
        },
        {
          type: 'note',
          text: 'Every amount is rounded to the cent item by item. Rates, bases and mandate type are **copied into the statement** when it is computed: changing the profile afterwards does not alter an issued statement; a draft uses the current values when you recalculate it.',
        },
        {
          type: 'paragraph',
          text: 'Some amounts may be hidden by your role permissions: they are then not shown.',
        },
      ],
    },
    {
      id: 'carry-over',
      title: 'Skips and carry-over',
      blocks: [
        {
          type: 'paragraph',
          text: 'If an agent balance is zero or negative, or below the threshold, or the profile or the ENASARCO parameters are missing, **the statement is not created** (you see it in the run outcome) but the movements stay open: the next run adds them to the new accruals. A reversal is therefore never lost.',
        },
      ],
    },
    {
      id: 'states',
      title: 'States and actions',
      blocks: [
        {
          type: 'table',
          headers: ['State', 'What you can do'],
          rows: [
            ['**Draft**', '**Recalculate** (picks up new movements up to the end date and redoes the figures), edit the **notes**, add or fix **adjustments**, **Issue**, **Delete draft** (the movements become open again).'],
            ['**Issued**', 'The statement gets its progressive **number** (n/year, per company) and becomes immutable. The only action is **Cancel statement**.'],
            ['**Cancelled**', 'The reason is mandatory. The movements become open again and can end up in a new statement; the number is not reused.'],
          ],
        },
        {
          type: 'paragraph',
          text: '**Issue** asks for confirmation, recalculates one last time and is not possible if the taxable amount is zero or negative. If an action is no longer allowed (for instance someone already issued the draft) an error message appears and the statement stays as it is. Buttons only appear if your role has the permission.',
        },
      ],
    },
    {
      id: 'adjustments',
      title: 'Adjustments',
      blocks: [
        {
          type: 'paragraph',
          text: 'An **adjustment** is a manual correction with a **sign**: positive increases what is due to the agent, negative reduces it. It needs an amount (not zero), a date and **mandatory notes** (max 500 characters).',
        },
        {
          type: 'list',
          items: [
            '**From the Statements or Accruals page** (**New adjustment**): pick agent and company; the adjustment stays open and enters the first suitable run.',
            '**Inside a draft**: agent and company are those of the draft, the adjustment is attached right away and the draft is recalculated. Until the draft is issued you can **edit** or **delete** adjustments from the movements list.',
          ],
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module under development',
      blocks: [
        {
          type: 'note',
          text: 'Module under development. Statement printing and email, registration of the agent document and payment will arrive in upcoming versions.',
        },
      ],
    },
  ],
}

export default guide
