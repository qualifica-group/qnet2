import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-requests',
  title: 'RDA - Purchase requests',
  summary: 'Purchase requests: filling them in, line approval, documents and closing.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Purchasing › RDA**. Each **RDA** (purchase request) has a header, one or more **lines** to buy, a footer with notes and conditions and some **documents**. The assigned **Function manager** approves or rejects the single lines; whoever has the fulfilment permission moves them to **Ordered**, **Received** or **On hold**.',
        },
        {
          type: 'paragraph',
          text: 'From the list you can filter, sort and export. The **status tabs** at the top show only the RDAs that have at least one line in that status. Expand a row with the arrow to see its lines and change their status without opening the RDA. You only see the RDAs you requested, manage or created, unless you are allowed to see everything.',
        },
      ],
    },
    {
      id: 'create',
      title: 'Create an RDA',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New RDA** and fill in the header: **Subject**, **Request date**, **Priority**, **Requester** and the organization fields (**Company**, **Company site**, **Operational site**, **Business function**).',
            'The **Function manager** is picked among the **requester’s direct superiors** (company profile in **Users**): with a single superior it is proposed automatically, and changing the requester updates the list. If the requester has no superiors the manager of the **Business function** is used; if that is missing too, a message under the field says so and the RDA cannot be saved. The requester cannot approve their own RDA. **Company site** only lists the sites of the chosen company.',
            'Optional: **Customer** and **Work order**. The **Supplier** is no longer in the header but on each line. The **+** next to a field creates a new supplier, customer or product on the spot.',
            'Add the lines (at least one) and the documents if needed, then press **Save**.',
          ],
        },
        {
          type: 'note',
          text: '**Created by** is read-only. Fields your role cannot edit appear disabled or hidden.',
        },
      ],
    },
    {
      id: 'lines',
      title: 'The lines',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each line can have a catalog **Product** or just a free **Description**. Picking a product proposes its description, **Unit**, **Unit price** and **VAT rate**: they stay editable. Each line can have a **Supplier** (supplier registries only), so one RDA can gather lines from different suppliers. On desktop each line is a single band under one shared header; on small screens lines become cards. Line actions are **icon-only**: hover to read their name.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['**Taxable / VAT / Total**', 'Computed by the system (quantity × price, then VAT) and read-only. The RDA total is the sum of its lines.'],
            ['**Extract VAT**', 'If you only know the final price including VAT, enter it and press Extract VAT: the price is brought back to the taxable amount with the line VAT rate (122.00 at 22% becomes 100.00). It needs a VAT rate above zero.'],
            ['**Supplier**', 'Optional when you create the line, but **required to move it to Ordered**: if missing, the status dialog asks for it line by line. It can be edited while the line is To approve (by whoever can edit the RDA) and Approved (by whoever has the fulfilment permission, even with the rest of the line locked); from **Ordered** on it is locked. Picking a product that has a supplier proposes it.'],
            ['**Total**', 'Hover (or focus with the keyboard) the total to see the line taxable amount and VAT.'],
            ['**Reason**', 'The speech-bubble icon opens a small panel with the reason text; it is highlighted when a reason is written and read-only on locked lines.'],
            ['**Status**', 'To approve, Approved, Ordered, Received, Rejected or On hold. It is not edited like a field: use the **Change line status** icon.'],
            ['**ODA**', 'Read-only: shows the code of the ODA generated from the line, as a link to the order, or a dash if the line is not linked. The link is removed if the ODA is deleted or cancelled, or if the line is removed from the ODA.'],
            ['**Line documents**', 'Attachments of the single line.'],
            ['**Change line status**', 'Shown on a saved line only when you can change its status: the assigned function manager approves or rejects lines To approve, whoever has the fulfilment permission moves Approved lines to Ordered or On hold and Ordered lines to Received or On hold. Pick the new status and an optional reason. Choosing **Ordered** adds a **Supplier** column to the dialog: lines that already have one show it, lines without one get a picker, and the confirm button stays disabled until none is missing.'],
            ['**Trash**', 'Deletes the line, if your role allows it and it is To approve or Rejected.'],
          ],
        },
        {
          type: 'warning',
          text: 'A line can be edited **only while it is To approve**: after approval or rejection it stays read-only. A **closed** RDA is entirely read-only.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documents',
      blocks: [
        {
          type: 'paragraph',
          text: 'Documents attach to the RDA and to its single lines. Until the RDA is saved the chosen files wait **in a queue** and are uploaded right after the save; if an upload fails, a notice tells you and you can retry from the saved RDA.',
        },
      ],
    },
    {
      id: 'close',
      title: 'Closing, sending and deleting',
      blocks: [
        {
          type: 'list',
          items: [
            '**Automatic closing**: the RDA closes by itself when no open lines are left, that is all of them are in a final status (Received, On hold or Rejected), even after deleting lines and saving.',
            '**Close RDA**: manual closing. If non-final lines remain the closing is **forced** and the **reason is mandatory**. A closed RDA is never reopened.',
            '**Send to manager**: sends the Function manager again the notification (app and email) received on creation.',
            '**Delete**: removes the RDA with its lines, history and documents after confirmation; not possible when a line is already Ordered or Received.',
          ],
        },
        {
          type: 'tip',
          text: 'Lines are approved and fulfilled one at a time with **Change line status** in the RDA, or in bulk from the **RDA line management** page (see its guide). The RDA must be saved first; the function manager must be the signed-in user.',
        },
      ],
    },
  ],
}

export default guide
