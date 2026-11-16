import type { HelpGuide } from '../../types'
import { recurringEmailSections } from './parts/work-orders-recurring-emails'
import { taskSections } from './parts/work-orders-tasks'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Work orders',
  summary: 'The work to carry out on a won contract.',
  sections: [
    {
      id: 'editing-a-work-order',
      title: 'Editing a work order',
      blocks: [
        {
          type: 'paragraph',
          text: "The work order detail is organised in tabs as in the old QNet. Under the header (title, status, indicators) there is a row of tabs: **Info**, **Tasks**, **Documents** (Work order documents and Registry documents), **Administration** (Costs, Active proforma and Passive proforma), **Notes**, then **Chat**, **Email** and **Activity log** where available. Each tab appears only if you may see it; when a group has a single item available, the tab shows that item directly. Tabs behave like pages: entering the work order opens **Info**; reloading the page keeps you on the tab you were on, and a save inside a tab does not bring you back to Info. At the end of the row, set a little apart, is the **work order title**: clicking it takes you back to Info.",
        },
        {
          type: 'paragraph',
          text: "The **Info** tab has two columns as in the old QNet. On the left: **Client info** (**Company** with the link to the registry, **Registered office** with VAT number and tax code, **Tags**, **Email/PEC**, **Phone**, **Referent 1** i.e. the registry's primary referent, **Referent 2** i.e. the next linked referent, **Commercial**, **Sector** and **Comments** i.e. the registry's general notes; tap an email, PEC or phone to write or call), then **Work order contract data** (**Amount** i.e. the total net amount of the products, visible to whoever may see the contract data, Start date, the link to the Contract, the **Product lines**, then every product of the work order with its payment fields and the totals Total Consulenza, Total Ente and Total revenue), **Work status** and **Team** (Supervisors and Participants). On the right: **Company references and business functions** (Company, Site, Operational site, Business function: the business functions of the Product lines), **Callback date**, **Work order description** (Work order no., Work order type and the description) and below them the flexible fields (**Additional information**), when the product lines bring any. Client info comes from the registry and is edited there; the references and Product lines come from the quote, read only; a work order with lines from several quotes does not show them. Address, VAT number, tax code and contacts show only if you may view registries and referents. The work order has no Context (Source), Rewards, Document and payment or Task template sections.",
        },
        {
          type: 'note',
          text: "The **Payment status** is not a field of the work order but of **each product**: you find it in **Work order contract data**, under the product. It is no longer a flexible field (neither in the Additional information nor among the categories' flexible fields): the values it had were copied onto the work order's products that had no status.",
        },
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a work order is edited **directly from its detail**, one field at a time, **Additional information** included (every flexible field has its own row). A section tied to a yes/no field appears only while the field is on: switching it off clears the values of the section. On the detail the Additional information is always open. On the create form, clicking the **Additional information** title collapses or reopens the section: the choice is kept in the browser and applies to every work order.',
        },
        {
          type: 'steps',
          items: [
            'Open the work order from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text and date fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it as it was, without saving.',
          ],
        },
        {
          type: 'tip',
          text: "The **Work order notes** sit in the yellow box at the top of the detail, like the General notes in Request management: when empty the box invites you to write them; with the pencil (or a click on the text) you write them right inside the box.",
        },
        {
          type: 'note',
          text: "Whoever is **added** as **Supervisor** or **Participant**, when the work order is created or later (from the list too), receives a **notification** in the bell and by email with the link to the work order. People already on it, a Participant who only changes position and anyone removed receive nothing; nobody is notified of their own changes. Someone who is both Supervisor and Participant receives one notification per role. When the work order is created from a **Task template**, each Supervisor also receives **a single** notification with the number of tasks assigned to them (e.g. \"assigned you the 21 tasks of the work order\"), not one per task.",
        },
        {
          type: 'note',
          text: "When you add a **Supervisor** to a work order (at creation, from the detail or from the list), the **Data di assegnazione** (assignment date) field of the Additional information takes today's date, even if it already had one. If you type the date yourself in the same save, yours is kept. Removing a Supervisor or adding only Participants does not change the date; work orders whose lines do not carry the field are not changed.",
        },
        {
          type: 'note',
          text: 'A field without a pencil cannot be edited by you: your role makes it read-only, or it is chosen only at creation (Work order no., Linked offer). The **Task template** is changed from the **Tasks** tab (see **Tasks: loading a template**). Contract (code and title) and the sections taken from the quote come from the offer, as does the Contract expiry shown among the indicators at the top (edited on the contract); Status and Progress come from the progress records (see the dedicated section).',
        },
        {
          type: 'note',
          text: 'The **Product lines** are chosen when the work order is created and cannot be changed afterwards: in the detail you see them in the **Contract data** section. The Additional information the lines bring in is filled in at creation (the required ones are needed to save).',
        },
      ],
    },
    {
      id: 'row-actions',
      title: "Table actions",
      blocks: [
        {
          type: 'paragraph',
          text: "The **Actions** column of the Work orders list (also in the Work orders sections of a Contract and of a Registry) has the actions of the old QNet: the **eye** opens the work order, **Supervisors and Participants** opens a popup with the people of the work order, **Info** opens the work order on the Info tab. The three-dots menu opens the work order straight on the **Notes history**, **Tasks** or **Documents** tab.",
        },
        {
          type: 'paragraph',
          text: "In the **Supervisors and Participants** popup you add or remove Supervisors and Participants with the pencil, under the same rules as the detail. At the bottom, if you may request proformas, there is **Request proforma**; **Close** closes the popup.",
        },
        {
          type: 'note',
          text: "Tasks and Documents show in the menu only if you can see those tabs. Force close, Progress, Request update, Request proforma and Activity are no longer row actions: you find them in the work order detail. The progress also opens from the **Progress** cell of the table.",
        },
      ],
    },
    {
      id: 'list-editing',
      title: "Quick editing from the list",
      blocks: [
        {
          type: 'paragraph',
          text: "Clicking an editable cell (Title, Work order type, Callback date, Start date, Supervisors, Participants) edits it **directly in the list**, with the **same rules as the detail**: for example the Title and the Start date are mandatory, while the Supervisors and Participants can all be removed; an invalid value is refused and the cell goes back to its previous value.",
        },
        {
          type: 'note',
          text: "**Force closing** is done from the work order detail (it asks for a reason), not from a cell. The **Registry** column shows the work order's client, taken from the linked offer: it can be filtered and sorted but not edited. The same goes for the **Contract expiry** column, the expiry date of the contract the work order comes from (date filter, empty when the contract has no expiry). The **Period** column shows the contract period the work order was programmed in: **Initial** or **Renewal N** (see the Contracts guide, Renewing the contract). Work order no., Contract no., Linked offer, Registry, Contract expiry, Period, Status, Progress, Created at and Updated at stay read-only.",
        },
      ],
    },
    {
      id: 'people-filters',
      title: "Filtering by person",
      blocks: [
        {
          type: 'paragraph',
          text: "In the **Advanced filters** panel (button in the bar above the list) the **Supervisors** and **Participants** fields let you pick one or more people: the list shows the work orders where at least one of them holds that role. If you fill in both fields, a work order must satisfy both. The **Supervisors** and **Participants** columns of the list also have their own filter by name, with a **(Blanks)** entry for the work orders with nobody in that role.",
        },
      ],
    },
    {
      id: 'work-phase-tabs',
      title: "Working phases",
      blocks: [
        {
          type: 'paragraph',
          text: "At the top right of the **Work orders** page you find the tabs **All**, **To plan**, **In progress**, **Completed**, **Cancelled** and **Terminated**. The phase is the work order **Status** (the **Status** column of the table and the badge at the top of the detail), separate from the **Working status** and from progress, and moves on its own as in the old QNet: a new work order is **To plan**; it becomes **In progress** when you add a **Supervisor**; a forced closure moves it to **Completed** (positive closing status) or **Cancelled** (negative); reopening it brings it back to **In progress**, or **To plan** when it has no Supervisors. From the contract: **Cancelled** moves all its work orders to **Cancelled**; **Terminated** moves to **Terminated** only those to plan or in progress, and reactivating the contract puts them back. **All** removes the filter. Every tab except **All** shows how many work orders it holds (only when not zero): the number follows the search and the active filters, so it matches what you see when you open the tab. The tab adds up with the other filters, **Parent category** included: if a work order is missing, check those too.",
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Default filters, category and client",
      blocks: [
        {
          type: 'paragraph',
          text: "When it opens, the **Work orders** page shows every record your profile lets you see, without an **Assignment** filter: if you may see everything you see everything, otherwise you see the work orders where you are **Supervisor** or **Participant** (and those of your site or competence, when your profile grants it). In the **Advanced filters** panel **Parent category** starts empty, and empty stands for every parent category you are enabled on: if you are enabled on all of them you see the work orders of every category, otherwise only the work orders of your categories (with all their subcategories). Opening the field's list you find only the parent categories you can pick: all of them if you are enabled on everything, otherwise only yours. Pick one or more and press **Apply** to narrow it down: your choice is saved. The **Search** field also finds work orders by client (registry) name: part of the name is enough, for example \"napoli\".",
        },
        {
          type: 'paragraph',
          text: "If you may see everything (for example with the admin role) you are enabled on every parent category, whatever your competence. Otherwise the categories you are enabled on are the competence categories of your profile (the assignment scope); if you have no competence categories **Parent category** limits nothing. The filters never widen what your profile can see.",
        },
        {
          type: 'paragraph',
          text: "In the same panel you can also pick one or more values in **Product category** (that category only) and **Client**. The filters never widen what you can see: your profile's visibility rules still apply. A work order falls under a category if the category is among the products on the **revenue rows of the offers it comes from**: the linked offer and, for a multi-offer work order, the offers of the rows it holds. Cost rows do not count. For a multi-offer work order the client of each offer counts too.",
        },
        {
          type: 'paragraph',
          text: "The flexible-field columns appear at the end of the list when the category filter points to a single category: a single value in **Product category** or, if that is empty, a single current parent category in **Parent category** (for example ISO). Choosing a current parent category also brings in the records of the linked legacy categories (for example [OLD] ISO), which do not appear as separate options in the filter, and the columns are those of the current category first, then those of the legacy ones. With two different current parent categories you see the records of both but no columns; the same with an empty **Parent category**. To see only the records of a legacy category or only of a new one use **Product category**. A field with the same name in several categories can appear twice. They are the work order's own fields: they can be sorted, filtered and exported; if you may edit work orders they are edited in the cell, except when the field does not concern that work order (the cell stays read-only). The values can still be read in the detail too.",
        },
        {
          type: 'note',
          text: "The limit to your categories with an empty **Parent category** applies only on the **Work orders** page: on **Project work orders** the empty field does not filter (its list still shows only the parent categories you are enabled on), and the field does not appear in the **Work orders** sections of a contract or a client. The export follows the active filters.",
        },
      ],
    },
    {
      id: 'force-close',
      title: 'Forced closure and reopening',
      blocks: [
        {
          type: 'paragraph',
          text: 'The forced closure is an **action**, not a field: you find it at the top of the work order detail. It shows only if you may edit the work order.',
        },
        {
          type: 'steps',
          items: [
            'Press **Force close**.',
            'Write the **reason** (required). If the work order still has open tasks, the dialog tells you how many will be closed with a negative outcome.',
            'Pick the **Closing status** (required): the list shows the **Closed (won)** and **Closed (lost)** statuses of the work order\'s set and starts on the main lost one. With a won status the work order progress goes to **100%** (an entry dated today is added, unless it was already at 100%).',
            'Press **Close work order**: the status becomes Closed, the Processing status becomes the closing status you picked and the reason appears among the work order details.',
          ],
        },
        {
          type: 'note',
          text: 'On a force-closed work order the action becomes **Reopen**: after the confirmation the closure is undone and its reason cleared; if the Processing status is a **Closed (lost)** status it goes back to **Open**. Tasks the forced closure closed stay closed.',
        },
      ],
    },
    {
      id: 'progress',
      title: 'Work order progress',
      blocks: [
        {
          type: 'paragraph',
          text: "Progress is recorded by hand and is the **sum** of the percentages of all the records (in the popup, the Percentage column is the increment of each row). The table's **Progress** column and the indicator at the top of the detail show the total and the date of the latest record; with no records the table cell shows a **+** icon and progress is 0%.",
        },
        {
          type: 'steps',
          items: [
            'Open the **Work order progress list**: click the **Progress** cell of the table, or press **Work order progress** at the top of the work order detail (next to Force close).',
            'The **Total % reached** field shows the sum already recorded. Type the new total (1 to 100) in **Current status %**: it must be greater than the total reached. Enter the **Date** (required).',
            'Press **Confirm**: the record is saved (as the difference from the total) and the popup closes.',
            "To delete a row press the trash in the Actions column, then confirm with the trash or cancel with the X: the total drops by that row's increment.",
          ],
        },
        {
          type: 'note',
          text: 'You record and delete records if you may edit the work order, otherwise you only see the list. Progress does not change the work order status (see **Working phases**), nor do task statuses; the tasks percentage stays visible in the task board.',
        },
      ],
    },
    {
      id: 'request-update',
      title: 'Update request',
      blocks: [
        {
          type: 'paragraph',
          text: 'With **Request update** you ask for news on the work order: the recipients get an email and a notification with your message and the link to the work order. The button is at the top of the work order detail; it shows only if you hold the **Request updates** permission and only on a **To plan** or **In progress** work order (not on a Completed, Cancelled or Terminated one). The work order is not changed.',
        },
        {
          type: 'table',
          headers: ['Recipients', 'Who gets the request'],
          rows: [
            ['Participants', 'Every participant; the supervisors still get a copy, marked **CC**.'],
            ['Supervisors', 'Every supervisor of the work order.'],
            ['Participants and supervisors', 'Both groups, as direct recipients.'],
          ],
        },
        {
          type: 'note',
          text: 'The message is **required** (3 to 2000 characters). A group with nobody in it cannot be picked; the number next to each group tells how many people get it.',
        },
      ],
    },
    {
      id: 'processing-status',
      title: 'Processing status',
      blocks: [
        {
          type: 'paragraph',
          text: "Besides the **Status** (the phase: To plan, In progress, Completed, Cancelled, Terminated, see **Working phases**), every work order has a **Processing status** field: the operational status you choose. You find it in the detail and as a table column, with a colored badge and the name.",
        },
        {
          type: 'paragraph',
          text: "The options come from the **status set** matching the work order by business function and category of its scheduled lines (see the **Work order status configurator**); with no match the default statuses apply. A new work order starts as **Open**. In the detail and in the table you can only pick the statuses of its own set, if you may edit the field.",
        },
        {
          type: 'note',
          text: "Picking by hand a **Processing status** with a **progress %** above zero raises the work order progress to that percentage (an entry dated today is added). If the status percentage is not above the progress already reached the change is refused and the field shows the error; statuses without a percentage change the status only. The same happens automatically when a task created from a template whose row is linked to a status is closed positively (see **Task Templates**): in that case the status is applied even when it comes before the current one. On a force-closed work order the progress does not change. If the work order data change and the applicable set is different, the status becomes the equivalent one (Open, Closed (won), Closed (lost), also for the other closing statuses of the same group) or Open. Force-closing sets the Processing status to the **Closing status** you picked (a **Closed (won)** status, which brings the progress to 100%, or a **Closed (lost)** one); reopening the work order sets it back to **Open** while it is still on a lost status.",
        },
      ],
    },
    {
      id: 'creating-a-work-order',
      title: 'Creating a work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'The create form looks like the detail: the same sections and rows, **closed**. Open a row with the pencil, fill the field and press **Done** to keep it (or **Revert** to put it back as it was). The **Work order no.** is already suggested. The suggested number is provisional: if you keep it and another user takes it in the meantime, saving assigns the next free number.',
        },
        {
          type: 'note',
          text: 'In **Offer and product lines** choose the offer first, then its lines: the Additional information appears at the bottom as soon as the lines provide for it. **Save** at the top (or bottom) checks every field and creates the work order; errors appear under the rows to fix. Leaving without saving asks for confirmation.',
        },
      ],
    },
    {
      id: 'automatic-title',
      title: 'Automatic title',
      blocks: [
        {
          type: 'paragraph',
          text: 'The work order **Title** is optional. If you leave it empty, the work order gets the automatic **code - products of its lines** title (e.g. COM-0042 - ISO 9001 + SOA), which follows the lines when they change.',
        },
        {
          type: 'note',
          text: 'If you write your own title, it stays yours and is no longer recalculated. To go back to the automatic one, **clear the field** and save. Existing work orders keep the title they have. The same applies to the contract **Program** window.',
        },
      ],
    },
    ...taskSections,
    {
      id: 'costs',
      title: 'Costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, **Costs** is a tab of **Administration**, together with Active proforma and Passive proforma (if you may see only the Costs, the top tab is **Costs** itself). The **Contract data** is not a tab: it sits in the **Info** tab, in the **Contract data** section. The **Costs** tab compares the **budgeted** costs (the offer cost lines imputed to the work order\'s revenue lines) with the **actual** costs entered here. It only appears if you may view costs.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['Variance', 'Actual cost minus budgeted cost: a positive value is an **overrun** (flagged with an icon and text, not by colour alone).'],
            ['Margin', 'Revenue of the work order lines minus costs. Revenue is the net amount, except on lines with a **received** supplier commission, where it is the supplier commission alone (as in the Contract data). Other commissions are excluded.'],
            ['Unattributed', 'Actual costs with no reference offer line: they are part of the total actual cost.'],
            ['Total actual cost', 'Sum, net of VAT, of three sources: the **manually entered costs**, the lines of the work order\'s **committed ODA** (approved, sent to supplier, partially or fully fulfilled) **not yet invoiced** (the committed amount of a line is the ordered quantity not yet billed times the unit price, or the received quantity if the residual is closed), and the lines of the work order\'s **passive invoices and proformas**. An ODA line that has been invoiced counts once, with the amount of the passive document. Drafts, ODA in validation and cancelled ones are excluded.'],
            ['Actual margin', 'Revenue minus the total actual cost; the total variance is the total actual cost minus the budgeted cost.'],
            ['By cost type', 'A table with, for each **cost type** (colour and nature: direct, indirect, general), the amount of the three sources and the total. Costs without a type are in the **No cost type** row. The budget is not split by cost type.'],
          ],
        },
        {
          type: 'note',
          text: 'Generic offer costs not imputed to any revenue line are shown separately as information and are not part of the comparison totals.',
        },
        {
          type: 'steps',
          items: [
            'Open the **Actual costs** tab: a single panel with the **Product catalogue** on the left and the actual costs as cards on the right (stacked on narrow screens). Without the permission to manage costs the catalogue is hidden and the cards are read-only.',
            'In the **Product catalogue** search by name or description: each product shows name, code, type, cost price with unit of measure, category and description.',
            'Tick one or more products and press **Add N selected**: each product becomes a cost with today\'s date, quantity 1, cost price, VAT, unit of measure and the product\'s **cost sub-account**. The same product can be added more than once.',
            "If the product has suppliers, the **supplier** is chosen only among the product ones: the default and its cost are proposed and, when you change supplier, the price changes. If the supplier has a Supplier commission on that product, the price is 0 and cannot be edited, with the notice next to the supplier. With a product without suppliers, or no product, the supplier is free as before.",
            'Each card shows product, code and category at the top with the bin to remove it; below, quantity times unit price, VAT, **Reference offer line** and net amount, VAT and total; at the bottom date, **cost sub-account**, **supplier**, **document reference** and extra description. The **Cost total** is at the bottom of the panel.',
            'To change product, remove the card and pick the product from the catalogue.',
            'Press **Save** to replace the whole list of costs, or **Cancel** to go back to the last saved state.',
          ],
        },
        {
          type: 'tip',
          text: 'Actual costs can also be entered on a completed or closed work order, since they often arrive after closure.',
        },
      ],
    },
    {
      id: 'contract-data',
      title: 'Work order contract data',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the **Info** tab of the work order detail, in the left column, the **Work order contract data** section shows **every product of the work order** one below the other, with the same fields as the old QNet. The product name is a **link** to the product.',
        },
        {
          type: 'table',
          headers: ['Field', 'Meaning'],
          rows: [
            ['Title', 'Product code and name with the typology badge (for example **Ente**); on a multi-offer work order also the client of the line.'],
            ['Amount', 'The net amount of the line, as saved on the quote. The tooltip shows the calculation, for example "2 × 500.00 = 1,000.00".'],
            ['Payment status', 'The payment status of the product (badge in the status colour), or **No status**. With the permission to manage payments a pencil sits beside it.'],
            ['Document', 'The work order proforma or invoice billing the product, with its number and payment status (Paid, Not due, Overdue, Seriously overdue); when there are several, the newest. **No proforma issued** when the product has not been billed yet.'],
            ['Payment agreement', 'The text of the payment agreement of the product.'],
            ['Unpaid', '**Yes** when the product has unpaid amounts, otherwise **No**.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Without the permission to see the work order **costs** or **contract data** you see only the **Title** and **Payment status** of each product, with no amounts, document, agreement or unpaid flag, and you cannot edit the payments. Under the products come the totals, as in the old QNet: **Total Consulenza**, **Total Ente** (one per typology: the net amount, with the revenue beside it when different; those at 0.00 are dimmed), then, when visible, Commissions and Net of commissions, finally **Total revenue**, each with its tooltip explaining the calculation.',
        },
        {
          type: 'warning',
          text: 'A warning icon beside the product (with the text in its tooltip) appears when a line with a received Supplier commission has no commission (revenue 0.00) or when the commission was calculated on a previous base: save the quote again to update it.',
        },
        {
          type: 'steps',
          items: [
            'Press the pencil beside the product **Payment status** (only with the permission to manage payments): the **Line payment** popup opens.',
            'Choose the **payment status**, write the **payment agreement** and say whether there are **unpaid** amounts.',
            'Press **Save** to save the product and close the popup, or **Cancel** to discard the changes.',
          ],
        },
        {
          type: 'paragraph',
          text: '**All products at once**: with the permission to manage payments and more than one product, above the products there is the **Set payment on every line** button. It marks every product in one go, for example as **Paid** or as **Unpaid**.',
        },
        {
          type: 'steps',
          items: [
            'Press **Set payment on every line**: in the popup choose the **payment status** (also **No status**) and/or the **unpaid** flag (Yes/No). A field left on **Keep unchanged** stays as it is on every product.',
            'Press **Apply**: the values are saved on all the products together (if one cannot be saved, none changes).',
          ],
        },
        {
          type: 'note',
          text: 'The payment agreement is a different text on every line: it is edited only in the **Line payment** popup. When a bulk action makes several lines deliverable, supervisors and participants receive **a single notification** listing the products.',
        },
        {
          type: 'note',
          text: 'Payment statuses are configured in **Work order payment statuses**. When a line moves to a status flagged **Can be delivered**, the work order supervisors and participants are notified. Payment data can also be edited on a completed or closed work order.',
        },
      ],
    },
    {
      id: 'invoices',
      title: 'Active and passive proforma',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, in the **Administration** tab next to **Costs**, the **Active proforma** and **Passive proforma** tabs list the documents tied to the work order. They only appear if you may view proforma and invoices (permission "View proforma and invoices"). They are **read-only**: nothing can be created, issued, collected or paid from here.',
        },
        {
          type: 'table',
          headers: ['Tab', 'What it shows'],
          rows: [
            ['Active proforma', 'The proforma and invoices issued to the customer for the work order: type, number, date, external number, customer, total, collected, residual and collection status, with a **Totals** row at the bottom.'],
            ['Passive proforma', 'The supplier proforma and invoices with **at least one line on the work order**: type, number, date, supplier, document total, **Work order share** (the sum of the document lines placed on this work order), paid and residual, with a **Totals** row.'],
          ],
        },
        {
          type: 'note',
          text: 'A proforma that gets registered stays in the table, now shown as an **Invoice**. A supplier document shows up as soon as one of its lines is on the work order, even if the other lines belong to other work orders: that is why the document total and the work order share can differ.',
        },
        {
          type: 'paragraph',
          text: 'The **number** of the document is a link: if you may view active invoices (or passive invoices) it opens the document in a side panel, where the usual actions remain subject to their own permissions; otherwise it is plain text.',
        },
      ],
    },
    {
      id: 'multi-quote-work-order',
      title: 'Multi-offer work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'A **multi-offer** work order gathers lines from several offers (and therefore several customers). It is created from the **Contracts** table with **Schedule single work order** (see the Contracts guide) and is recognised by the **Multi-offer** badge.',
        },
        {
          type: 'list',
          items: [
            'In the detail there are no customer, contract, company and site sections: each product line shows, under its name, the **registry** it refers to, linked to its record.',
            'After creation, lines can only be **removed** (at least one must remain): lines from other offers cannot be added.',
            'In the list the Customer and Offer columns show several values separated by " · "; filtering by one of the customers finds the work order. The contract expiry stays empty.',
            'The work order appears in the **Work orders** tab of the contract of each source offer.',
            'The cost budget is made of the cost lines of **all** the source offers.',
            'Tasks generated from the template have no customer.',
            'The Email tab has no customer or offer variables and the recipients are not pre-filled.',
          ],
        },
        {
          type: 'note',
          text: 'Proforma requests of a multi-offer work order are one per offer: see the Proforma Requests guide. An offer with lines in a multi-offer work order cannot be deleted.',
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Proforma request',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you can create proforma requests, the **Request proforma** button is at the top of the work order detail, next to **Work order progress**, and at the bottom of the **Supervisors and Participants** popup of the Work orders list (see **Table actions**): both open the same window.',
        },
        {
          type: 'steps',
          items: [
            'Press **Request proforma**: the **Proforma issue request: Work order #number** window opens.',
            'Check the **Payment method** taken from the quote (**Not specified** appears when it is missing).',
            'Write the **Notes for Accounting** (required, up to 5000 characters): the text is prefilled with the window title.',
            'Press **Send request**. The system creates one request for the Consultancy lines and one for each supplier of the Institution lines (the supplier chosen on the offer line).',
          ],
        },
        {
          type: 'note',
          text: 'When a request is still pending, the window shows **Last request on** and the date: another one cannot be sent and **Send request** stays disabled. A work order generates its requests only once: once they are fulfilled no new ones can be generated.',
        },
        {
          type: 'tip',
          text: 'Sent requests are listed in **Accounting › Receivables › Proforma requests**.',
        },
      ],
    },
    {
      id: 'deleting-a-work-order',
      title: 'Deleting a work order',
      blocks: [
        {
          type: 'warning',
          text: 'A work order is deleted by selecting it in the list and using **Delete selected** (you need the delete permission). It **cannot be deleted** if it has linked proforma requests, active invoices, purchase orders or purchase invoices: in that case it stays and the message counts it among the ones not deleted. Tasks, hours and manually entered costs do not block deletion.',
        },
      ],
    },
    {
      id: 'project-work-orders',
      title: 'Project work orders and Work Orders',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Work Orders** page shows only **processing** work orders. Work orders of type **Project** have their own page, **Project Work Orders**, with the **Unread** column and the **Chat** tab in the detail; for now that page does not appear in the menu. Inside a **Contract** and a **Registry**, the **Work orders** sections still show work orders of every type: you can reach project work orders from there.',
        },
        {
          type: 'note',
          text: "The **Chat** tab exists only on project work orders: processing work orders do not have it.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Which work orders you see",
      blocks: [
        { type: 'paragraph', text: "What you see depends on your role. You always need the **View** permission; the levels are cumulative:" },
        { type: 'table', headers: ["Permission", "Visible work orders"], rows: [["**View**", "Those where you are a **Supervisor** or a **Participant**."], ["**View by site**", "Your own plus those whose quote belongs to one of your sites. For a work order with several quotes, one is enough."], ["**View by competence**", "Your own plus those where at least one source quote has a category in your assignment scope, in any site. With **Scope on all categories**, all of them."], ["**View all**", "All of them."]] },
        { type: 'paragraph', text: "The filter applies to the list, the detail, filters, exports, statistics and dashboard, selectors and documents. Opening a link to a work order you cannot see shows an access error. Seeing a work order does not let you edit or delete it: that takes the **Edit** and **Delete** permissions, and you can only edit what you can see." },
        { type: 'paragraph', text: "Whoever sees a work order also sees its tasks on the task board and on the **Tasks** page, if they can view Tasks, even with no role on the task; private tasks stay excluded. Editing them takes a role on the task or the Tasks **Manage all** permission. Exception: a **Supervisor** or a **Participant** can complete a task of the work order that has no assignees, and completing it makes them its assignee." },
        { type: 'note', text: "If you create a work order without being a Supervisor or a Participant, you will no longer see it unless you have **View all**, **View by site** or **View by competence**." },
        { type: 'tip', text: "Whoever can edit the work order can involve a colleague who cannot see it by mentioning them in a note with **Mention anyone**: on save they become a **Participant** (see **Notes and documents** in the general guide)." },
      ],
    },
    ...recurringEmailSections,
    {
      id: 'in-development',
      title: 'Module in development',
      blocks: [
        {
          type: 'note',
          text: 'This module is under development. The guide will be published once the module is complete.',
        },
      ],
    },
  ],
}

export default guide
