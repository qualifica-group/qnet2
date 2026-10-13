import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-orders',
  title: 'ODA',
  summary: 'Purchase orders to suppliers: creation, lines, statuses, merging, PDF printing and sending.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Purchasing › ODA** and lists the **purchase orders** sent to suppliers. An ODA is **only the order**: it never turns into an invoice. The purchase invoice is a separate document and a single invoice can collect several ODA.',
        },
        {
          type: 'paragraph',
          text: 'The **status tabs** at the top narrow the list to one status (**All** shows them all). The arrow at the left of each row opens its **lines**. You can filter, sort and export; at the bottom you find the taxable, VAT and grand totals of the current filter.',
        },
        {
          type: 'table',
          headers: ['Icon', 'Action'],
          rows: [
            ['**View** / **Edit**', 'Opens the order page (read-only if it cannot be edited).'],
            ['**Download PDF**', 'Generates the order PDF.'],
            ['**Delete**', 'Deletes the order after confirmation, where allowed.'],
            ['**Activity**', 'Shows the change history.'],
          ],
        },
      ],
    },
    {
      id: 'create',
      title: 'Creating an ODA',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New ODA** and fill in the **Header**: **Supplier**, **Company**, **Company/Site**, **Order date** and **Description** are required; **Operational site**, **Payment method**, **Internal user**, **Customer**, **Work order**, **Contract/Offer**, **Opportunity**, **EA tags** with **Specification** and **General notes** are optional. **Company/Site** only lists the sites of the chosen company.',
            'Add at least one line (see **Lines**) and, if needed, the **Documents**: they stay queued and are uploaded right after saving.',
            'Optional: in the **Copies** field enter 1 to 12 (see below), then press **Save**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'If you pick a supplier that is **not qualified** a dialog opens: **Abandon** clears the field, **Proceed anyway** keeps the supplier and leaves a red warning under the field. It is a warning only, not a block. The **+** button next to supplier, customer and product creates a new record on the fly.',
        },
        {
          type: 'paragraph',
          text: '**Monthly copies**: with **Copies** greater than 1 the same number of independent ODA is created, each with its own number. The **Order date** moves forward one month per copy (31 January, 28 February, 31 March…). Queued documents are uploaded only on the first one. The field exists only when creating.',
        },
        {
          type: 'note',
          text: 'Fields your role cannot edit appear disabled or hidden.',
        },
      ],
    },
    {
      id: 'lines',
      title: 'Lines',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each line can have a catalogue **Product** or just a free **Description** (required). Choosing a product proposes the **description**, **U.m.**, **price** (the product purchase cost) and **VAT rate**: all stay editable. Each line can have its own **Work order**, proposed from the header one.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['**Cost type**', '**Required** on every line. It shows in the type’s color in the picker. Types are managed under Purchasing › Cost types (see its guide).'],
            ['**Taxable / VAT / Total**', 'Computed by the server (quantity × price, then VAT) and read-only; the preview in the form matches the saved result. Header totals are the sum of the lines.'],
            ['**Extract VAT from price**', 'If you only know the VAT-inclusive price, enter it and press the icon: the price goes back to the taxable amount using the line rate (122.00 at 22% becomes 100.00). A rate above zero is needed.'],
            ['**RDA #n badge**', 'Appears on lines generated from a purchase request (see **Link with RDA**).'],
          ],
        },
        {
          type: 'note',
          text: 'Each ODA has 1 to 200 lines. Quantity above zero, price not negative.',
        },
      ],
    },
    {
      id: 'numbering',
      title: 'Numbering',
      blocks: [
        {
          type: 'paragraph',
          text: 'On creation each ODA gets a progressive number per **company** and **order-date year**, in the form **n/year** (for example 4/2026). The number is assigned once: it does not change if you later edit the date. The **company** is chosen on creation and **cannot be changed** afterwards.',
        },
      ],
    },
    {
      id: 'status',
      title: 'Statuses and transitions',
      blocks: [
        {
          type: 'paragraph',
          text: 'The status cannot be edited from the form: use **Change status** on the order page, which only offers the steps allowed by the workflow and by your permissions. An order starts as **Draft**.',
        },
        {
          type: 'note',
          text: 'The status names in this guide are the **default** ones: under **Purchasing › ODA statuses** they can be renamed, recolored, given another icon and reordered. Tabs, badges and the **Change status** dialog follow the configured name, color, icon and order at once. The allowed steps and the rules below do not change.',
        },
        {
          type: 'table',
          headers: ['Status', 'Can move to'],
          rows: [
            ['**Draft**', 'In validation, Approved, Cancelled'],
            ['**In validation**', 'Draft, Approved, Cancelled'],
            ['**Approved**', 'In validation, Sent to supplier, Cancelled'],
            ['**Sent to supplier**', 'Partially fulfilled, Fulfilled, Cancelled'],
            ['**Partially fulfilled**', 'Sent to supplier, Fulfilled'],
            ['**Fulfilled**', 'Partially fulfilled'],
            ['**Cancelled**', 'Draft'],
          ],
        },
        {
          type: 'warning',
          text: '**Sent to supplier** can only be reached from **Approved**. Moving an order to **Approved** requires the dedicated approval permission. Header, lines, deletion and merging are possible **only in Draft or In validation**: in any other status the order is read-only.',
        },
        {
          type: 'note',
          text: 'Sending the email to the supplier does not change the status by itself: change it by hand.',
        },
        {
          type: 'paragraph',
          text: 'Statuses also affect the linked RDA lines (see **Link with RDA**): **Cancelled** frees them, **Fulfilled** moves them to **Received**. Going back from **Fulfilled** to **Partially fulfilled**, or from **Cancelled** to **Draft**, does not change the RDA.',
        },
      ],
    },
    {
      id: 'merge',
      title: 'Merge ODA',
      blocks: [
        {
          type: 'steps',
          items: [
            '**Save** the order changes first: merging takes effect immediately and, with unsaved changes, the button warns you and does not open.',
            'Open the destination order page (in Draft or In validation) and press **Merge**.',
            'The dialog lists the other ODA of the **same supplier and the same company** in Draft or In validation, with their lines: select single lines or a whole ODA.',
            'Confirm with **Merge N lines**: the lines are **moved right away** to the end of the current order and the totals of both orders are recalculated.',
          ],
        },
        {
          type: 'list',
          items: [
            'Besides the **Merge** permission on the destination you need the **edit** permission on the source ODA and the **delete** permission on those left without lines: if one is missing the operation is refused and nothing is moved.',
            'ODA left **without lines** are **deleted** (with their documents); sectors and documents of the source ODA are not moved.',
            'A source ODA **linked to a purchase invoice or proforma** cannot be emptied by merging: the operation is refused and nothing is moved.',
            'Lines linked to an RDA keep the link, which now points to the destination order.',
            '**Merge history** (shown when the order has some): lists, most recent first, the merged orders with the moved lines, totals and who performed the operation.',
          ],
        },
      ],
    },
    {
      id: 'rda-link',
      title: 'Link with RDA',
      blocks: [
        {
          type: 'paragraph',
          text: 'ODA are also created from the **RDA line management** page with the **Generate ODA** action (see its guide): approved RDA lines are grouped by supplier and each supplier gets an ODA **in Draft**, with the header taken from the RDA. The RDA lines move to **Ordered** and their **ODA** column shows the code as a link. RDA documents are not copied: they remain available from the RDA.',
        },
        {
          type: 'paragraph',
          text: 'In the ODA the generated line shows the **RDA #n** badge; description, quantity and price stay editable.',
        },
        {
          type: 'warning',
          text: '**Deleting a linked line**, **deleting the ODA** or **cancelling it** frees the linked RDA lines: an **Ordered** line goes back to **Approved**, so it can generate a new order; if its RDA is already **closed** it goes to **On hold** instead. Received, Rejected or On hold lines keep their status and only lose the reference to the ODA.',
        },
        {
          type: 'paragraph',
          text: 'A **cancelled** ODA loses its link to the RDA lines even if you reopen it (back to Draft): the RDA lines do not change. Moving the ODA to **Fulfilled** turns the **Ordered** RDA lines into **Received**, and the RDA closes by itself if all its lines are concluded. Going back to **Partially fulfilled** does not change the RDA.',
        },
      ],
    },
    {
      id: 'delete',
      title: 'Deletion',
      blocks: [
        {
          type: 'paragraph',
          text: 'An ODA can only be deleted in **Draft** or **In validation**, after confirmation. It cannot be deleted if it is **linked to a purchase invoice or proforma** (it appears under **Accounting › Payable › Installments**, column **Linked POs**): the operation is refused with a message. Linked RDA lines are freed as described in **Link with RDA**.',
        },
      ],
    },
    {
      id: 'register-invoice',
      title: 'Registering the invoice',
      blocks: [
        {
          type: 'paragraph',
          text: 'On the page of an ODA that is **Approved**, **Sent to supplier**, **Partially fulfilled** or **Fulfilled** the **Register invoice** button appears (it requires the permission to create passive invoices). It opens the passive invoice editor with the **order lines already filled in** and the ODA already linked; from there you can add other ODAs of the same supplier and company, and edit, add or remove lines.',
        },
        {
          type: 'note',
          text: 'An order line can be invoiced **only once**: lines already on another passive invoice are not offered again. See the **Passive invoices** guide.',
        },
      ],
    },
    {
      id: 'pdf-email',
      title: 'PDF printing and sending to the supplier',
      blocks: [
        {
          type: 'paragraph',
          text: '**Print PDF** (on the order page) and **Download PDF** (in the list) generate the document named **ODA-number-year**. You need an active (or default) **Layout** of the **ODA** module under **Configuration › Layouts**; without one, printing is refused.',
        },
        {
          type: 'paragraph',
          text: '**Send to supplier** opens the email window with the **PDF attached** and the supplier email as recipient: edit subject and text, then send. The action requires the send permission and is available **only when the ODA is Approved or Sent to supplier**: in other statuses the button does not appear.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'table',
          headers: ['Permission', 'Allows'],
          rows: [
            ['**View**', 'See all ODA and download the PDF.'],
            ['**Create**, **Edit**, **Delete**', 'Manage header and lines; also create from RDA line management.'],
            ['**Change status**', 'Every status step except approval.'],
            ['**Approve**', 'Move the order to Approved.'],
            ['**Merge**', 'Use Merge ODA.'],
            ['**Send to supplier**', 'Send the email with the PDF.'],
            ['**Register invoice** (Create permission of passive invoices)', 'Open the passive invoice editor from the ODA.'],
            ['**Export**, **View activity**', 'Export the list and see the change history.'],
          ],
        },
        {
          type: 'tip',
          text: 'Whoever can view sees all ODA, not only their own.',
        },
      ],
    },
  ],
}

export default guide
