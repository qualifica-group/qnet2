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
          text: 'The module is under **Warehouse & Equipment › Purchasing › RDA**. Each **RDA** (purchase request) has a header, one or more **lines** to buy, a footer with notes and conditions and some **documents**. The assigned **Function manager** approves or rejects the single lines; whoever has the fulfilment permission moves them to **Ordered**, **Received** or **On hold**.',
        },
        {
          type: 'paragraph',
          text: 'From the list you can filter, sort and export, and **edit directly in the cells** Subject, Request date, Priority, Requester, Function manager, Customer, Work order, Company site (only among the sites of the company of the RDA), Operational site and Business function: double-click the cell, pick the value and confirm. The same rules as the record apply; closed RDAs and fields your role cannot edit stay read-only. The **Company** is changed from the record, together with its site. The **status tabs** at the top show only the RDAs that have at least one line in that status. Expand a row with the arrow to see its lines and change their status without opening the RDA. You only see the RDAs you requested, manage or created, unless you are allowed to see everything.',
        },
        {
          type: 'note',
          text: 'The names, colors and icons of the line statuses (To approve, Approved, Ordered, Received, Rejected, On hold) are the defaults and can be customized in **Configuration › Purchasing › RDA line statuses**: tabs, badges and the change status dialog follow the configuration. What the statuses mean and the allowed transitions do not change.',
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
            'Press **New RDA** and fill in the header: **Subject**, **Request date**, **Priority** (picked from the configurable list, prefilled with the default one and shown as a colored badge), **Requester** and the organization fields (**Company**, **Company site**, **Operational site**, **Business function**).',
            'The **Function manager** is picked among the **requester’s direct superiors** (company profile in **Users**): with a single superior it is proposed automatically, and changing the requester updates the list. If the requester has no superiors the manager of the **Business function** is used; if that is missing too, a message under the field says so and the RDA cannot be saved. The requester cannot approve their own RDA. **Company site** only lists the sites of the chosen company.',
            'Optional: **Customer** and **Work order**. The **Supplier** is no longer in the header but on each line. The **+** next to a field creates a new supplier, customer or product on the spot.',
            'Add the lines (at least one) and the documents if needed, then press **Save** (at the top or at the bottom of the record). Leaving without saving asks for confirmation.',
          ],
        },
        {
          type: 'note',
          text: '**Created by** is read-only. Fields your role cannot edit appear disabled or hidden.',
        },
      ],
    },
    {
      id: 'edit',
      title: 'Editing an RDA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Opening a saved RDA shows its **record**: status, actions and totals at the top; below, the **Request**, **Organization and references**, **Notes and conditions**, **Lines** and **Documents** sections. There is no general Save button: every field is edited on its own.',
        },
        {
          type: 'steps',
          items: [
            'Hover the field and press the **pencil** (or click the value): the field opens for editing.',
            'Change the value and press **Save**: only that field is saved. **Cancel**, the Esc key or a click outside the field leave it as it was.',
            '**Company** and **Company site** are edited together. Changing the **Requester** or the **Business function** also shows the **Function manager**, which realigns as on creation.',
          ],
        },
        {
          type: 'note',
          text: 'One field opens at a time: opening another one discards the unsaved changes of the first. Without the edit permission, or on a closed RDA, no pencil is shown.',
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
            ['**Supplier**', 'Optional when you create the line, but **required to move it to Ordered**: if missing, the status dialog asks for it line by line. It can be edited while the line is To approve (by whoever can edit the RDA) and Approved (by whoever has the fulfilment permission, even with the rest of the line locked); from **Ordered** on it is locked. Picking a product with suppliers proposes the default supplier and its cost as the price. When you change supplier, if it is one of the product ones the price becomes its cost; an outside supplier leaves the price as it is.'],
            ['**Total**', 'Hover (or focus with the keyboard) the total to see the line taxable amount and VAT.'],
            ['**Reason**', 'The speech-bubble icon opens a small panel with the reason text; it is highlighted when a reason is written and read-only on locked lines.'],
            ['**Status**', 'To approve, Approved, Ordered, Received, Rejected or On hold. It is not edited like a field: use the **Change line status** icon.'],
            ['**ODA**', 'Read-only: shows the code of the ODA generated from the line, as a link to the order, or a dash if the line is not linked. The link is removed if the ODA is deleted or cancelled, or if the line is removed from the ODA.'],
            ['**Line documents**', 'Attachments of the single line.'],
            ['**Change line status**', 'Shown on a saved line only when you can change its status: the assigned function manager approves or rejects lines To approve, whoever has the fulfilment permission moves Approved lines to Ordered or On hold and Ordered lines to Received or On hold. Pick the new status and a reason, **mandatory** for **Rejected** and **On hold** (optional for the other statuses). Choosing **Ordered** adds a **Supplier** column to the dialog: lines that already have one show it, lines without one get a picker, and the confirm button stays disabled until none is missing.'],
            ['**Trash**', 'Deletes the line after confirmation, if your role allows it and it is To approve or Rejected.'],
          ],
        },
        {
          type: 'warning',
          text: 'On the record the lines are shown read-only: the line **pencil** opens it for editing, **Save** saves that line alone and **Cancel** puts it back. **Add line** opens a new line the same way; the **trash** asks for confirmation and deletes right away. A line can be edited **only while it is To approve** (on an Approved line only the supplier changes, if you may): after approval or rejection it stays read-only. A **closed** RDA is entirely read-only.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documents',
      blocks: [
        {
          type: 'paragraph',
          text: 'Documents attach to the RDA and to its single lines. On a saved RDA uploading and deleting are immediate. Until the RDA is saved the chosen files wait **in a queue** and are uploaded right after the save; if an upload fails, a notice tells you and you can retry from the saved RDA.',
        },
      ],
    },
    {
      id: 'notes',
      title: 'Notes',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the right column of the detail there is the **Notes** tab, the first one: here you discuss the request with your colleagues, mentioning them with **@** as in Opportunities. From the table you open the same notes with the row **Notes** action, which shows how many there are. The notes written in the old QNet come with the migration.',
        },
        {
          type: 'tip',
          text: 'The Notes tab and action appear to whoever can see the purchase request (requester, function manager, author or whoever sees every purchase request); writing a note also needs the permission to create notes. The notes differ from the header **Notes** field, which stays a text of the record.',
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
            '**Automatic closing**: the RDA closes by itself when no open lines are left, that is all of them are in a final status (Received, On hold or Rejected), even after deleting lines.',
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
