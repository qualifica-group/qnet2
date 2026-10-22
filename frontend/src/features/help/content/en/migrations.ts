import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrations',
  summary: 'The Migrations module imports data from an external system into qnet, usually at startup: roles, users, companies, sites, referents, products, registries, opportunities, quotes and work orders.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Develop › Migrations** and is restricted to the **super-admin** role. Each **Source** represents a type of data to import: Roles, Users, Business functions, Companies, Company sites, Operational sites, Referent types, Referents, Sources, Tags, Sectors, Task templates, VAT rates, Payment methods, Attributes, Product categories, Attribute layouts, Products, Cost products, Email templates, Document bundles, Registries, Opportunities, Quotes, Work orders, Tasks, Commission configurations, Purchase requests, Purchase orders, Financial accounts, Purchase invoices, Proforma requests and Invoices.' },
        { type: 'note', text: 'The sources with **— link** in their name create no new records: they link records already imported, so run them after the sources of those records. **Business functions — link manager & operators** assigns the manager and the operators to the business functions (after **Users**). **Product categories — link attributes** associates the attributes with the categories (after **Attributes** and **Product categories**). **Products — link suppliers** sets the supplier, a registry, on the imported products (after **Products** and **Registries**); a supplier already chosen in QNet is never overwritten.' },
        { type: 'note', text: 'Product categories, products and cost products imported from the legacy are for **history** only. Their name is preceded by **[OLD]**, for example **[OLD] ISO**, and the categories arrive **not active**: together with their branch and their products they disappear from every create and edit field, but they stay on the opportunities, offers and work orders already imported and in the grid filters. A legacy category is never merged into a QNet category with the same name: it is created separately. Every legacy category sits under a single category, **Old category**, not active: those imported earlier, which sat under **Consulenza**, are moved there by the next import of the categories. The legacy APL branch is called **[OLD] APL** and its products stay there. Every legacy category keeps its business function, **Formazione** and **APL** included: there are no separate functions for history. The **Formazione** and **APL** functions are created at installation and the **Business functions** source recognises them by name, also when written in capitals (**FORMAZIONE**), instead of creating a second one.' },
        { type: 'note', text: 'The **Cost products** source imports the legacy QNet costs: warehouse articles, vehicles, equipment, expense reports and employee levels (the hourly cost used by employee costs). Each item becomes a product **Usable as cost** (Costs tab of the offer only) under the **[OLD] Costi** category (inside **Old category**), not active, in the **[OLD] Articoli**, **[OLD] Veicoli**, **[OLD] Attrezzature**, **[OLD] Note spese** and **[OLD] Dipendenti** sub-categories; an article lands in an [OLD] Articoli sub-category named after its legacy category. License plate, serial number, brand, model, barcode, ministerial code and storage position become product attributes. For articles the cost is the legacy purchase price. Run **VAT rates** first, otherwise the rate stays empty with a warning. For new offers the installation separately creates the active **Costi** category, with the cleaned-up legacy cost items in the **Note spese** and **Articoli** sub-categories: the costs of imported offers and work orders stay linked to the [OLD] products.' },
        { type: 'note', text: 'The **Email templates** and **Document bundles** sources import the legacy QNet email templates and document bundles, the latter together with all their files. A name already in use gets a numeric suffix; a file that is too large or has a disallowed format is skipped with a warning instead of failing the row.' },
        { type: 'note', text: 'The **Attribute layouts** source imports the Work order sections of all legacy cards (ISO, SOA, Safety, Avvalimenti, GDPR, R&D, PAL, Subsidized finance, Tenders, Site partnerships, Education, GOL Training) and, for the Offer, the "Preliminary info" of the legacy opportunity (ISO with the groups of the category standard only and the Sites table, SOA, Avvalimenti, GDPR, R&D, Tenders, Partnerships, Projects, Education). The **Processing status** field receives the statuses of the family (root) of the category. A category receives the fields (Work order and Offer) only from the cards of its own products. Run **Attributes**, **Product categories** and **Product categories — link attributes** first: a layout using a field not linked to the category is discarded. An existing layout is never overwritten. The field values of individual work orders come with the **Work orders** source; the GOL Training fields of the Offer are not imported; the free notes of the Preliminary info go to the general notes of the opportunity. The fields and layouts of the Training branch configured in QNet are not modified.' },
        { type: 'note', text: 'A product without a category in the legacy is imported into the **No categoria** category, created at installation with the **No funzione aziendale** business function. With the first product imported the category becomes **not active**, like the rest of the legacy catalogue: you can still move a product from there to the right category. Thanks to that function, the legacy offers containing these products are imported all the same. In the legacy these are mostly discounts, tests and service entries.' },
        { type: 'note', text: 'The **Users** source recognises the users already in QNet, for example those created at installation: first by email, then by first and last name among the users not linked to the legacy yet. A recognised user gets the legacy id, so the later sources link it; only empty company, site and manager are filled, while role, password, email and contacts stay as in QNet. When several users share the name the row fails, naming them; users not recognised are created. An **active** user not recognised has **[OLD]** before its name, for example **[OLD] Mario Rossi**: it is a legacy account, separate from the QNet ones. When a person has several users in the legacy, the active one is linked first.' },
        { type: 'note', text: 'When the installation is configured for **link only**, the **Users** source links the users already in QNet: a recognised user gets the legacy id and nothing else, so company, site and manager are not filled. Users not recognised are created as in the full import but always disabled, with a warning in the report. In this mode the **Business functions — link manager & operators** source is not available: function managers and operators are managed in QNet, so the **Import this source** button is disabled and **Import all** skips it even when it is included in the order.' },
        { type: 'note', text: 'The **Task templates** source imports the template, its phases, activities and sub-activities. A template whose title contains "non attivo" arrives deactivated. A title already used by another template gets the "(old_id N)" suffix. A sub-activity with an issue (missing parent, parent in another template, a cycle) becomes a top-level activity instead, with a warning in the report.' },
      ],
    },
    {
      id: 'status-and-requirements',
      title: 'What is migrated and what it needs',
      blocks: [
        { type: 'paragraph', text: 'The **Migration status** box lists every source by phase, in the order to import them, and shows how many are already migrated. Each source shows its state: **Migrated**, **Migrated with failed rows**, **Last import failed**, **In progress** or **Not migrated**, with the import date. Click a source to select it.' },
        { type: 'paragraph', text: 'The **What it takes to migrate well** box explains what the selected source needs and lists the sources to import first, each with its state. A missing **Required** source stops its rows from being imported; a missing **Recommended** one leaves the link empty, with a warning in the report. When a required source is missing, the box says so in red.' },
        { type: 'tip', text: 'A source stays **Migrated** even when a later import fails: the date is the last completed import.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Step 1: review and preview',
      blocks: [
        { type: 'steps', items: ['Open **Develop › Migrations**.', 'In the **Source** field, choose the type of data to import, for example **Users** or **Products**.', 'The **Expected template** box lists the fields qnet expects from the external system.', 'Click **Show external preview**. Use **Previous** and **Next** to browse the data.'] },
        { type: 'note', text: 'Nothing is created during this step.' },
      ],
    },
    {
      id: 'import-step',
      title: 'Step 2: importing',
      blocks: [
        { type: 'steps', items: ['After checking the preview, click **Import this source**.', 'Read the confirmation window and click **Start import**.', 'Follow the progress. At the end you see the summary: **Total rows**, **Created**, **Skipped** and **Failed**.', 'Check **Warnings and errors** for rows with problems.'] },
        { type: 'tip', text: 'The import keeps running even if you close the window. Records already imported are skipped, so you can repeat the import without creating duplicates.' },
        { type: 'paragraph', text: 'In addresses the municipality is recognized even when the legacy QNet spells it differently (capital letters, accents, apostrophes, a trailing province code, a postal code instead of the municipality), and a missing province, region and country are filled in. The country arrives for every legacy nation. If the municipality is not recognized, the address is imported without it, with a warning.' },
      ],
    },
    {
      id: 'operational-records',
      title: 'Registries, opportunities, quotes and work orders',
      blocks: [
        { type: 'paragraph', text: 'The **Registries**, **Opportunities**, **Quotes** and **Work orders** sources import the operational data of the legacy QNet. Run them in this order and after **Users**, **Referents**, **Sources**, **Tags**, **Sectors**, **Operational sites**, **Product categories**, **Products**, **VAT rates**, **Payment methods** and **Company sites**: each source links the records already imported by the previous ones.' },
        { type: 'list', items: [
          '**Registries**: a private customer becomes an individual card, the others a company card. The registered office is the primary address, the other legacy sites become additional addresses. Placeholder VAT numbers (0, 1, dot) are not imported. The legacy tags go into the registry\'s **Tag** custom field: run the **Tags** source first; if the field is not defined the tags are not imported and the report says so.',
          '**Opportunities**: all those with a customer are imported. GOL course requests without a customer fail with the reason. A customer can have several open opportunities. An opportunity takes the categories of the products on the lines of its quotes; only when its quotes have none does it take the legacy opportunity\'s own product categories. Running the import again completes the already imported opportunities that have no categories (warning "Missing product categories back-filled."); those that already have categories are left untouched. The **General notes** receive the legacy notes, the reserved notes and the **Notes** field of each Preliminary info card, under the card title. Running the import again adds the missing Preliminary info notes to the already imported opportunities (warning "Missing preliminary info notes back-filled.").',
          '**Quotes**: the quotes of the imported opportunities, with code **QUO-** followed by the legacy id, the revenue lines, VAT and the commissions that have a percentage or an amount. The status follows the legacy one: declined becomes closed lost; accepted or contracted becomes closed won and creates the contract with the legacy status and dates; accepted by the customer goes to the first pending status of the workflow (or the first validated one, when there is none). A presented quote follows the legacy opportunity phase: Offer sent goes to **In trattativa**, Call back to **Da Richiamare**, Stand-by to **Rimandata** or **In Standby**, Lost to closed lost; Awaiting pro-forma and Accepted go to closed lost when another quote of the same opportunity is won, otherwise to **In trattativa**; the others stay open. When the quote workflow has no such status, the import creates it in the workflow as **[OLD] In trattativa**, **[OLD] Da Richiamare** or **[OLD] Stand-by** (once, before the closing statuses) and puts the quote there: you can then move the quotes to the workflow statuses and rename or delete the [OLD] status. For quotes already imported and still open, the `quotes:realign-legacy-status` command does the same move. A quote closed in the legacy system is closed even when none of its lines was imported, with a warning. The **quote date**, the **next callback** (the legacy opportunity callback date) and the **costs** come too: each legacy cost (expense reports and employees, with the hours as quantity and the hourly cost as price) becomes a Costs tab line with its cost product (run **Cost products** first), linked to the revenue line with the same product. When the supplier set in the legacy system differs from the product one, or the product has none, you get a warning and the supplier is not written. A quote cannot have a customer other than its opportunity one: when the legacy one differs you get a warning and the quote stays on the opportunity customer. The **Additional information** comes too: the values of the legacy opportunity preliminary info go on each of its quotes, only in the fields the categories of the quote products provide (run **Product categories — link attributes** first). A field not provided, an invalid value or a user not imported is skipped with a warning and the quote is imported anyway.',
          '**Work orders**: imported with code **COM-** followed by the legacy id, the linked quote lines, supervisors and participants. Work orders closed, cancelled or terminated in the legacy arrive force-closed with the reason. The **flexible fields** of the legacy work order come too (ISO with standards, certificates and inspections, SOA, Safety, Avvalimenti, GDPR, R&D, PAL, Tenders, Site partnerships, Education, GOL Training with the data of the course edition (name, status, dates, hours, decrees and credits), the **Processing status**, **Proforma emessa** and **Totale commessa manuale**), with the values as the legacy work order page shows them (for example an empty choice with its default, such as Off Site or No), only in the fields the categories of the work order lines provide: a field not provided, an invalid value or a user or registry not imported is skipped with a warning and the work order is imported anyway. Payment status, payment agreement and unpaid amounts go on every line of the work order instead. The **costs** of the legacy Offer panel (expense reports and employees) come as work order costs, in the Costs tab: each one once, on the work order that has the line of its service and linked to that line; a cost without a service, or of a service no work order has, goes to the first work order of the quote without a line. They also stay as Costs lines of the quote, so budget and actuals start equal and are then edited separately. A legacy work order without lines, or with a quote line not imported, fails with the reason. Supervisors are not mandatory: a work order without supervisors is imported; a supervisor or participant not imported (including one deleted in the legacy) fails the work order with the reason. The work order **stages** come too: legacy stages with the same name become a single stage, closed only when all of them were, with the legacy closing date and user (a user not imported leaves the stage closed without author, with a warning). Stages come without tasks: tasks are imported with the **Tasks** source. Only the work orders imported from now on receive flexible fields, stages, payment data and costs: those already imported stay as they are.',
          '**Tasks**: run it after Work orders (Users and Work orders are mandatory; it is better to have Registries, Referents and Opportunities first). All legacy tasks come, open and completed, with sub-tasks, assignees, watchers, requester, creator, type, category, priority, importance, status, dates and times, estimate, block and closing feedback, the work order stage (by name) and the active recurrences with the occurrences already generated. A task of a work order not imported, or with a user not imported, fails with the reason. Registry, referent, opportunity or stage not found stay empty with a warning; a sub-task whose parent is not imported becomes a top-level task, with a warning; a task with no creator in the legacy (no requester and no assignees either) arrives with **Utente legacy** as creator, a disabled user imported by the Users source. The Private activity flag is not imported (all tasks arrive as not private); notes, documents and timesheet hours are not imported.',
        ] },
        { type: 'note', text: 'No notifications or emails are sent and no activity log entries are written during this import. Notes and documents of work orders are not imported; tasks come with the **Tasks** source. No record is dropped silently: an opportunity, quote or work order that cannot be imported, even because of a single line, appears among the failed rows with the reason. A line already linked to another work order stays on the first one, and each later work order gets a copy of the line, with its commissions, added to the quote: the quote total grows accordingly.' },
      ],
    },
    {
      id: 'purchase-requests',
      title: 'Accounting: purchase requests',
      blocks: [
        { type: 'paragraph', text: 'The **Purchase requests** source is the first of the accounting part and runs after **Tasks**. **Users**, **Companies**, **Company sites**, **Operational sites**, **Business functions** and **Work orders** must already be imported; it is better to have **Registries** (customer and supplier) and **VAT rates** first.' },
        { type: 'note', text: 'The **Financial accounts** source imports the legacy bank accounts and cash box (the **Financial accounts** page) with name, IBAN, account number, address and notes. It is better to run **Companies** first: the company of the account is the one holding the same IBAN among the legacy company banks, else the one named in the account name. The company banks without a legacy account come too, as bank accounts of their company. An invalid IBAN stays empty with a warning; an account already created by hand with the same IBAN is linked to the legacy account instead of being duplicated. Cards are not imported.' },
        { type: 'list', items: [
          'All legacy purchase requests come, open and closed, with subject, date, priority, requester, function manager, creator, company, sites, business function, work order, customer and the texts (notes, delivery terms, procurement plan, technical requirements, special conditions). The legacy controller notes are appended to the notes.',
          'The **lines** come with description, supplier, unit of measure, quantity, price, VAT rate, reason, status and approver, together with the **history of the status changes**. Amounts and totals are recalculated by QNet.',
          'A legacy request without company or company site takes the site from the quote of its work order and the company from that site.',
          'A request closed in the legacy arrives closed with who closed it, the date and the reason. An open request whose lines are all received, rejected or on hold arrives closed, as QNet would do, with the author and date of the last status change.',
          'A request with a user, company, site, business function or work order not imported fails with the reason. Customer, supplier, VAT rate and unit of measure not found stay empty with a warning: to keep the “package” and “cubic metre” units, first create the codes **package** and **cubic_metre** in **Units of measure**.',
        ] },
        { type: 'note', text: 'No notifications are sent and no activity log entries are written. Purchase request attachments are not imported. Ordered or received lines get their linked purchase order when purchase orders are imported.' },
      ],
    },
    {
      id: 'purchase-orders',
      title: 'Accounting: purchase orders',
      blocks: [
        { type: 'paragraph', text: 'The **Purchase orders** source runs after **Purchase requests** and before the supplier invoices, which link to the orders. **Users**, **Companies**, **Company sites** and **Registries** must already be imported (the supplier is mandatory); it is better to have **Operational sites**, **Payment methods**, **VAT rates**, **Work orders** and **Purchase requests** first.' },
        { type: 'list', items: [
          'All legacy purchase orders come, also those already turned into a supplier invoice, with date, description, notes, supplier, customer, company, company and operational site, payment, internal user, sector notes, status and creator. The extreme urgency flag is appended to the notes.',
          'The **lines** come with description, cost type (the legacy order type: Collaboratori, Fitti, Utenze or Altro), quantity, price, VAT rate and work order. Amounts and totals are recalculated by QNet. The work order of the order is the one shared by all its lines.',
          'A line linked to an imported purchase request line points back to it: the request line shows the order code. When several order lines cite the same request line (for example the monthly copies), only the first stays linked.',
          'Each order keeps its legacy **number**. When the company already used it in the same year, the order takes the first free number after the highest, with a warning and the legacy number and protocol in the notes. The counter of new orders restarts from the highest number imported.',
          'A **fulfilled** order arrives with a receipt of every quantity, dated on the legacy last update. A **partially fulfilled** one arrives without receipts, with a warning.',
          'An order with supplier, company, company site or creator not imported, or with a cost type that does not exist, fails with the reason. Customer, operational site, payment, VAT rate, work order and request line not found stay empty, with a warning.',
        ] },
        { type: 'note', text: 'No notifications are sent and no activity log entries are written. Attachments are not imported. Invoice number, date, due dates and payment come with the **Purchase invoices** source; an order already turned into a supplier invoice keeps the ordered amounts.' },
      ],
    },
    {
      id: 'purchase-invoices',
      title: 'Accounting: purchase invoices',
      blocks: [
        { type: 'paragraph', text: 'The **Purchase invoices** source runs after **Purchase orders**: in the legacy system a supplier invoice (FTP) is the same document as its order, so in QNet it becomes an invoice linked to that order. **Users**, **Companies**, **Company sites**, **Registries**, **Payment methods** and **Purchase orders** must already be imported.' },
        { type: 'list', items: [
          'The FTP with the same supplier, number, year and company become **a single invoice**, linked to all their orders: for example a supplier invoice recorded in the legacy system over several orders.',
          'The lines bill the lines of their orders, with the invoiced amounts; the order keeps the ordered amounts. Amounts and totals are recalculated by QNet.',
          'The **due dates** come from the legacy system, in date order; when their sum differs from the total, the last one is corrected, with a warning. An invoice without due dates gets those of its payment method.',
          'An invoice **Paid** in the legacy system arrives with every due date paid, on the legacy last update date and without account, with a warning.',
          'As in QNet, the invoice records the **receipt** of what it bills, also on an order still in draft, which becomes fulfilled.',
          'An invoice with a zero total, with a number already used by the same supplier in the year, or with supplier, company, company site, payment method, creator or order not imported fails with the reason.',
        ] },
        { type: 'note', text: 'No notifications are sent and no activity log entries are written. The due dates of orders not invoiced yet, the “Validated, to pay” status and the attachments are not imported.' },
      ],
    },
    {
      id: 'active-invoices',
      title: 'Accounting: proforma requests and invoices',
      blocks: [
        { type: 'paragraph', text: 'The **Proforma requests** and **Invoices** sources import the legacy receivable cycle, in this order: the issue request comes from the work order, the document is linked to its request. **Users**, **Companies**, **Registries**, **Payment methods**, **VAT rates**, **Quotes** and **Work orders** must already be imported; it is better to have **Products** and **Financial accounts** first too.' },
        { type: 'list', items: [
          '**Proforma requests**: all of them come, issued and pending, with kind (consultancy or institution), institution, note, assignee and requester; the payment method is the quote’s.',
          '**Invoices**: the customer proformas come with their legacy number; those with the external invoice number (Fatture in Cloud) come as **invoices**, with external number and date. The **institution proformas** come addressed to the institution, with one line carrying the taxable amount, and take the first free number after the last one of the year.',
          'Lines (with product and quote line), due dates and the **collections** recorded on the legacy due dates come too. Amounts and totals are recalculated by QNet; when the due dates do not add up, the last one is corrected, with a warning.',
          'Each document is linked to the proforma request it came from, which becomes issued. The account of the document is the one of its legacy bank; a document without bank in the legacy system stays without account.',
          'A document with a zero or negative total, with a line without VAT rate, or with company, customer, payment method or creator not imported fails with the reason.',
        ] },
        { type: 'note', text: 'No notifications are sent and no activity log entries are written. Attachments, tags and collections not tied to a due date are not imported.' },
      ],
    },
    {
      id: 'mass-import',
      title: 'Importing everything at once',
      blocks: [
        { type: 'steps', items: ['Click **Configure order**.', 'Choose the sources to include, drag them into the right order and click **Save order**.', 'Click **Import all** and then **Start import**.'] },
        { type: 'paragraph', text: 'Sources are imported one after another. If one fails, the import stops and the following ones show as **Not run**.' },
        { type: 'tip', text: 'Put first the data the others depend on: roles before users, companies before sites.' },
      ],
    },
  ],
}

export default guide
