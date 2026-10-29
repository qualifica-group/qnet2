/**
 * "What it takes to migrate well" for every registered migration source,
 * shown in the source's template. The sources to import first come from the
 * backend (`dependencies`); this holds the prerequisites that are not sources
 * and the behaviour worth knowing. Mirrors `it-migration-requirements.ts` 1:1.
 */
export const migrationRequirements = {
  roles:
    'No prerequisites. A role with the same name already in QNet, super-admin included, is reused and its description is filled only when empty. Permissions are not imported: assign them in Administration › Roles.',
  users:
    'Without Roles, Companies and Operational sites, role, company and site stay empty with a warning. Each user’s manager is linked automatically at the end of the import. Email, first name and last name are required. A user already in QNet is recognised by email, or by first and last name when it has no legacy id yet: it gets the legacy id and only empty company, site and manager are filled; role, password and contacts stay as in QNet. When several users share the name, the row fails. An active user not recognised is created with [OLD] before its name. For new users the password must arrive already hashed (bcrypt). City and province are matched against the QNet list of municipalities.',
  'business-functions':
    'No prerequisites. Manager and operators are not linked here: after Users run “Business functions — link manager & operators”. On the first import the report lists the users not found yet: this is expected.',
  companies:
    'No prerequisites. The address is created only when it has a street. City and province are matched against the QNet list of municipalities: a name not found stays empty with a warning.',
  'company-sites':
    'Without Companies the site has no company, with a warning. City and province are matched against the QNet list of municipalities.',
  'operational-sites':
    'No prerequisites. The street is required: without it the row fails. City and province are matched against the QNet list of municipalities.',
  'business-function-members':
    'Creates no records: it links manager and operators to the business functions already imported. Operators are only added; the manager replaces the one set in QNet when different.',
  'referent-types': 'No prerequisites.',
  sources: 'No prerequisites. A source with the same name already in QNet is reused.',
  tags: 'No prerequisites.',
  sectors:
    'No prerequisites. The parent sector is linked automatically at the end of the import. Sectors already in QNet are not reused: they stay next to the imported ones, even with the same name.',
  'task-templates':
    'No mandatory prerequisites; to link each activity to its Work order processing status, import Work orders — workflows first. A title already in use gets the “(old_id N)” suffix; a title containing “non attivo” arrives deactivated. Running it again, templates already imported receive the missing statuses (a status already chosen is never overwritten; a template whose activities changed in QNet is left untouched, with a warning).',
  'vat-rates':
    'No prerequisites. The rate must be a number greater than or equal to zero, otherwise the row fails.',
  'payment-methods':
    'No prerequisites. A payment method with the same code already in QNet is reused.',
  attributes:
    'No prerequisites. An attribute with the same code already in QNet is reused.',
  'product-categories':
    'Without Business functions the category has no function, with a warning. The parent category is linked automatically at the end of the import. Every legacy category is created with the [OLD] prefix, even when QNet already has a category with the same name, and keeps its business function, Formazione and APL included. Categories of the [OLD] APL branch arrive not selectable.',
  'product-category-attributes':
    'Creates no records: it links the attributes to the categories already imported. Links already in QNet are not changed.',
  'attribute-layouts':
    'A layout using a field not linked to the category fails, so run “Product categories — link attributes” first. An existing layout is never overwritten.',
  products:
    'A product without a category in the legacy goes under the “No categoria” category, to reclassify it from there; a category not imported yet fails the row. Without VAT rates the rate stays empty with a warning. The supplier is set by “Products — link suppliers”.',
  'cost-products':
    'Without VAT rates the rate stays empty with a warning. It creates the Costi category and its sub-categories itself, with no need for Product categories. It also imports the employee levels used by the employee costs of quotes and work orders.',
  'email-templates':
    'No prerequisites. The body is cleaned as in the editor, losing images, tables and styles; a name already in use gets a suffix.',
  'document-bundles':
    'No prerequisites. Files are copied within the QNet attachment limits: a missing, too large or disallowed file is skipped with a warning.',
  referents:
    'Without Referent types the type stays empty with a warning. First and last name are required. City and province are matched against the QNet list of municipalities.',
  registries:
    'Without Users, Referents, Sources and Sectors, managers, supervisor, referents, source and sector stay empty with a warning: the registry is created anyway. The sector is matched by name among the imported sectors only. The name is required; placeholder VAT numbers (0, 1, dot) are not imported. No notifications are sent during the import.',
  opportunities:
    'Without its imported registry the row fails: run Registries first. Missing referents, users, source and operational site stay empty with a warning. An opportunity without a customer (for example a GOL course request) or with a category not imported fails with the reason.',
  quotes:
    'Without its imported opportunity the row fails. Even a single line that cannot be imported (no product, for example a discount, product not imported, commission recipient not imported, cost without a cost product) fails the whole quote with the reason. Missing VAT rates, payment methods, sites, referents and users stay empty with a warning. An accepted or contracted quote also creates the contract. A cost supplier other than the product one and a customer other than the opportunity one raise a warning.',
  'work-orders':
    'Without its imported quote the row fails. A quote line already linked to another work order is copied into the quote, with its commissions, and the quote total grows. The flexible fields come too, only those the categories of the work order lines provide (run Product categories — link attributes first): a field not provided, an invalid value or a user or registry not imported is skipped with a warning. The stages come too, open or closed as in the legacy system, without tasks. Payment status, payment agreement and unpaid amounts land on every line of the work order. The costs of the legacy Offer panel (expense reports and employees) come as work order costs, linked to the line of their service: a cost without an imported cost product fails the work order (run Cost products first). A work order without lines, or with a quote line not imported, fails with the reason. Documents are not imported; notes come with the Notes source and tasks with the Tasks source.',
  tasks:
    'Requires Users and Work orders already imported (mandatory); it is also better to import Registries, Referents and Opportunities first, and Work orders — workflows for the Work order processing status of the task (with which closing the task moves the work order forward). Running it again, tasks already imported receive the Work order processing status when missing (a status already set is never overwritten; the work order does not change). All legacy tasks come, open and completed, with sub-tasks, assignees, watchers, requester, creator, type, category, priority, importance, status, dates and times, estimate, block and closing feedback, the work order stage (by name) and the active recurrences with the occurrences already generated. A task of a work order not imported, or with a user not imported, fails with the reason. Registry, referent, opportunity or stage not found stay empty with a warning. A sub-task whose parent is not imported becomes a top-level task, with a warning. A missing creator becomes the user who runs the migration. No notification is sent. The “Private activity” flag is not imported (all tasks arrive as not private); documents and timesheet hours are not imported; notes come with the Notes source.',
  'work-order-progress':
    'Requires Work orders already imported (mandatory). Creates the progress history of each work order: one entry per day with the cumulative percentage (1 to 100), computed by the legacy by adding up the progress steps. A work order not imported fails; a work order that already has entries is skipped and left untouched. An invalid entry (percentage outside 1–100 or not increasing) fails the row and nothing is written. Entries of work orders no longer in the legacy fail with the reason. No author and no notification.',
  'work-order-workflows':
    'Requires Business functions already imported (mandatory); Product categories too, when a workflow is tied to a category (such as SOA). Creates one active workflow per legacy progress category, with its statuses in order and their progress percentage. A business function or category not imported fails the row; a workflow with the same criteria, or whose statuses were already imported, is skipped. No notification is sent.',
  'work-order-workflow-statuses':
    'Requires Work orders and Work order workflows already imported (mandatory). Sets the current working status of each work order, only when that status belongs to the workflow that applies to the work order: otherwise the work order keeps its status and the row returns a warning. A work order or status not imported fails the row. Orders no longer in the legacy fail with the reason.',
  'work-order-task-templates':
    'Requires Work orders and Task templates already imported (mandatory). Creates no records: it links each work order to the Task template its tasks came from in the legacy system, which does not store it and infers it from the tasks generated from a template. No task is created. A work order or template not imported fails the row; a work order that already has that template is skipped, one with another template chosen in QNet is left untouched and returns a warning. Work orders without tasks from a template are not sent.',
  'product-suppliers':
    'Creates no records: it sets the supplier on the imported products. Without the product the row fails; a supplier not yet imported from Registries is skipped with a warning. A supplier already chosen in QNet is never overwritten.',
  'commission-configurations':
    'Each rule needs its recipient (registry, referent or user) and its product or category already imported: when missing, the row fails. A rule on a parent category, such as ISO or GDPR, is imported and applies to all its subcategories. A rule without recipient becomes a rule for the whole role (the legacy sends only the most recent per role and category); rules of deleted recipients, without role or without value are not imported.',
  'purchase-requests':
    'Requires Users, Companies, Company sites, Operational sites, Business functions and Work orders already imported (mandatory); it is also better to import Registries (customer and supplier) and VAT rates first. All legacy purchase requests come, open and closed, with their lines, supplier, unit of measure, quantity, price, VAT rate, reason, status and approver of each line, and the history of the status changes. Amounts and totals are recalculated by QNet. A request with a user, company, site, business function or work order not imported fails with the reason. Customer, supplier, VAT rate and unit of measure not found stay empty with a warning: to keep the “package” and “cubic metre” units, first create the codes package and cubic_metre in Units of measure. An open request whose lines are all received, rejected or on hold arrives closed. No notification is sent. Attachments are not imported.',
  'purchase-orders':
    'Requires Users, Companies, Company sites and Registries already imported (the supplier is mandatory); it is also better to import Operational sites, Payment methods, VAT rates, Work orders and Purchase requests first. All legacy purchase orders come, also those already turned into a supplier invoice, with lines, cost type, quantity, price, VAT rate, work order and the link to the purchase request line. Amounts and totals are recalculated by QNet. Each order keeps its legacy number; when the company already used it in the same year, it takes the first free number after the highest, with a warning and the legacy number in the notes. A fulfilled order arrives with a receipt of every quantity; a partially fulfilled one arrives without receipts, with a warning. An order with supplier, company, company site or creator not imported fails with the reason. No notification is sent. Attachments, invoice data and due dates are not imported.',
  'financial-accounts':
    'It is better to import Companies first, to link each account to its company. The legacy bank accounts and cash box come with name, IBAN, account number, address and notes; the company is the one holding the same IBAN among the legacy company banks, else the one named in the account name. The company banks without a legacy account come too, as bank accounts of their company. An invalid IBAN stays empty with a warning. An account already created by hand in QNet with the same IBAN is not duplicated: it is linked to the legacy account. Cards are not imported and their data never leaves the legacy system.',
  'purchase-invoices':
    'Requires Users, Companies, Company sites, Registries, Payment methods and Purchase orders already imported; it is also better to import Operational sites, VAT rates and Work orders first. The legacy supplier invoices (FTP) come as invoices linked to their purchase orders: the FTP with the same supplier, number, year and company become a single invoice. Lines, invoiced amounts, due dates and paid status come from the legacy system; amounts and totals are recalculated by QNet. The invoice records the receipt of what it bills, also on a draft order, which becomes fulfilled. An invoice with a zero total, with a number already used by the supplier in the year or with a required reference not imported fails with the reason. No notification is sent. The due dates of orders not invoiced and the attachments are not imported.',
  'proforma-requests':
    'Requires Users, Quotes and Work orders already imported; it is also better to import Registries first (the institution of the institution requests). All legacy proforma issue requests come, issued and pending, with kind (consultancy or institution), institution, note, assignee and requester; the payment method is the quote’s. A request of a work order not imported, or with a user not imported, fails with the reason. No notification is sent.',
  invoices:
    'Requires Users, Companies, Registries, Payment methods and VAT rates already imported; it is also better to import Work orders, Quotes, Products, Financial accounts and Proforma requests first. The legacy customer proformas come with their number, and the institution proformas addressed to the institution and numbered after the last number of the year; those with the external invoice number come as invoices. Lines, due dates and the collections recorded on the due dates come too; amounts and totals are recalculated by QNet. Each document is linked to its proforma request, which becomes issued. A document with a zero or negative total, with a line without VAT rate or with a required reference not imported fails with the reason. No notification is sent. Attachments and tags are not imported; the collections not on the due dates come with the Collections source.',
  collections:
    'Requires the Invoices already imported. Every legacy collection comes, unfiltered: those already on the due dates are skipped, the missing ones are recorded on the open installments on the registration date (a partial collection leaves the residual on a new installment) and accrue the commissions. A collection without a linked document, or on a document deleted or not imported, fails with the reason. A collection beyond the document residual is limited to the residual, with a warning. The account of the collection is not imported.',
  notes:
    'Requires Users and Work orders already imported (mandatory); it is also better to import Opportunities, Tasks, Registries, Referents and Purchase requests first. The notes, calls and appointments written by hand in the legacy system on work orders, opportunities, tasks, registries, referents and purchase requests come, with author, date, mentions and images; calls and appointments start with the words “Chiamata” or “Appuntamento”. Replies are attached to the first note of their thread; a reply to an automatic entry (status change, document) becomes a note with that entry quoted at the top. A note on a record not imported, with an author not imported or replying to a note not imported fails with the reason. An author deleted in the legacy system becomes Utente legacy; the mention of a user not imported stays as text, with a warning. Sent emails and automatic entries are not imported. No notification is sent.',
}
