import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlements',
  title: 'Statements',
  summary: 'The commission settlement statements: from the draft to the agent document, the approval and the passive invoice.',
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
          text: 'The **tabs** above filter by state: **Drafts** (default), **Issued**, **Document received**, **Approved**, **Cancelled** and **All**. The list also has the **Document number** and **Payment status** columns. Below the table are the **totals** of the current filter; you can group by state, agent, company and month. From the row you open the statement record: state and the allowed action buttons on top, then the statement data, totals, movements and, with the permission, the change history.',
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
            '**ENASARCO** is computed on a base that does not exceed the yearly **ceiling**. The ceiling (single-firm or multi-firm) comes from the ENASARCO parameters of the period year, minus what is already covered by the **issued, document-received or approved** statements of the same agent and company in the same year. The total rate is applied to the base; the agent share is **withheld**, the principal share is **informative only** (it is a company cost).',
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
            ['**Draft**', '**Recalculate** (picks up new movements up to the end date and redoes the figures), edit the **notes** (pencil on the Notes row, then **Save**), add or fix **adjustments**, **Issue**, **Delete draft** (the movements become open again).'],
            ['**Issued**', 'The statement gets its progressive **number** (n/year, per company) and becomes immutable. You can **Register document** or **Cancel statement**.'],
            ['**Document received**', 'The agent invoice or fee note has been registered. You can **Approve** or **Remove document** (the statement goes back to **Issued**). It cannot be cancelled from here: remove the document first.'],
            ['**Approved**', 'The linked passive invoice has been created. The statement is **immutable** and cannot be cancelled.'],
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
            '**From the Statements or Accruals page** (**New adjustment**): a side sheet opens; open the Agent and Company rows, then Amount, Date and Notes, confirm each with **Done** and press **Save**; the adjustment stays open and enters the first suitable run.',
            '**Inside a draft** (**New adjustment** button on the Movements section): agent and company are those of the draft, the adjustment is attached right away and the draft is recalculated. Until the draft is issued you can **edit** or **delete** adjustments from the movements list.',
          ],
        },
      ],
    },
    {
      id: 'agent-document',
      title: 'Agent document',
      blocks: [
        {
          type: 'paragraph',
          text: 'When the agent sends the invoice or fee note, press **Register document** on an **Issued** statement. Enter **Document number**, **Document date** (not in the future) and the declared amounts: **Taxable amount**, **VAT**, **Withholding tax**, **ENASARCO agent** and **Document total**, which must equal taxable + VAT. The fields are prefilled with the statement amounts.',
        },
        {
          type: 'paragraph',
          text: 'The amounts are compared with the statement within the **configured tolerance** (normally 1.00 €, item by item). Beyond the tolerance saving is refused and the error names the item, the difference and the tolerance. Within the tolerance the difference is accepted: the statement **keeps its own amounts** and the **Agent document** section of the record shows the declared amounts and the **differences** from the statement.',
        },
        {
          type: 'paragraph',
          text: 'With **Remove document** (asks for confirmation) the document data is deleted and the statement goes back to **Issued**. The permissions to register and remove the document are required.',
        },
      ],
    },
    {
      id: 'approval',
      title: 'Approval and passive invoice',
      blocks: [
        {
          type: 'paragraph',
          text: 'On a **Document received** statement press **Approve**: the **Approve statement** window creates the **passive invoice** addressed to the agent’s supplier, with its installments in the payables schedule.',
        },
        {
          type: 'list',
          items: [
            '**Supplier registry**: if the referent already has a supplier registry linked, that is used. Otherwise the window offers the **candidate** registries (same tax code or VAT number as the referent) to link, or **Create from the referent** (the permission to create registries is also needed): the new registry copies the referent record, contacts and addresses included, and is a supplier. If the referent record is incomplete the message lists the missing fields. The link can also be changed from the referent’s **Supplier registry** field.',
            '**Payment method**, **Cost type** and **VAT rate** are suggested by the agent’s **fiscal profile** (see the Fiscal profiles guide), are required and can be changed.',
            'The **installments preview** shows the due dates generated by the payment method, computed on the **net payable**.',
            'The invoice has a single «Commissions» line for the period, with the number and date of the agent document, **Withholding tax** and **ENASARCO** taken from the document. The VAT of the line must be within the tolerance of the document VAT, otherwise the error appears on the VAT rate. A number already used for the same supplier in the year is refused.',
          ],
        },
        {
          type: 'paragraph',
          text: 'If creating the invoice fails the statement stays **Document received**: nothing is saved halfway.',
        },
      ],
    },
    {
      id: 'after-approval',
      title: 'After approval',
      blocks: [
        {
          type: 'list',
          items: [
            'The statement record shows the **Passive invoice** section with the link to the invoice, **Net payable**, **Paid**, **Residual** and the **Payment status**.',
            'The **payment status** is that of the invoice installments (installments are paid from **Accounting › Payable › Installments**): it is not stored on the statement. In the list you can filter by **Payment status**.',
            'An **Approved** statement is **immutable**: it cannot be recalculated or cancelled and the document cannot be removed.',
            'The linked passive invoice is locked: it cannot be edited or deleted and its number does not change; notes, attached documents, payments and the change of due date and method of unpaid installments remain allowed.',
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
          text: 'Module under development. Statement printing and email will arrive in upcoming versions.',
        },
      ],
    },
  ],
}

export default guide
