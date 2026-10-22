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
    'No prerequisites. A title already in use gets the “(old_id N)” suffix; a title containing “non attivo” arrives deactivated.',
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
    'Without its imported quote the row fails. A quote line already linked to another work order is copied into the quote, with its commissions, and the quote total grows. The flexible fields come too, only those the categories of the work order lines provide (run Product categories — link attributes first): a field not provided, an invalid value or a user or registry not imported is skipped with a warning. The stages come too, open or closed as in the legacy system, without tasks. Payment status, payment agreement and unpaid amounts land on every line of the work order. The costs of the legacy Offer panel (expense reports and employees) come as work order costs, linked to the line of their service: a cost without an imported cost product fails the work order (run Cost products first). A work order without lines, or with a quote line not imported, fails with the reason. Notes and documents are not imported; tasks come with the Tasks source.',
  tasks:
    'Requires Users and Work orders already imported (mandatory); it is also better to import Registries, Referents and Opportunities first. All legacy tasks come, open and completed, with sub-tasks, assignees, watchers, requester, creator, type, category, priority, importance, status, dates and times, estimate, block and closing feedback, the work order stage (by name) and the active recurrences with the occurrences already generated. A task of a work order not imported, or with a user not imported, fails with the reason. Registry, referent, opportunity or stage not found stay empty with a warning. A sub-task whose parent is not imported becomes a top-level task, with a warning. A missing creator becomes the user who runs the migration. No notification is sent. The “Private activity” flag is not imported (all tasks arrive as not private); notes, documents and timesheet hours are not imported.',
  'product-suppliers':
    'Creates no records: it sets the supplier on the imported products. Without the product the row fails; a supplier not yet imported from Registries is skipped with a warning. A supplier already chosen in QNet is never overwritten.',
  'commission-configurations':
    'Each rule needs its recipient (registry, referent or user) and its product or category already imported: when missing, the row fails. A rule on a parent category, such as ISO or GDPR, is imported and applies to all its subcategories. A rule without recipient becomes a rule for the whole role (the legacy sends only the most recent per role and category); rules of deleted recipients, without role or without value are not imported.',
  'purchase-requests':
    'Requires Users, Companies, Company sites, Operational sites, Business functions and Work orders already imported (mandatory); it is also better to import Registries (customer and supplier) and VAT rates first. All legacy purchase requests come, open and closed, with their lines, supplier, unit of measure, quantity, price, VAT rate, reason, status and approver of each line, and the history of the status changes. Amounts and totals are recalculated by QNet. A request with a user, company, site, business function or work order not imported fails with the reason. Customer, supplier, VAT rate and unit of measure not found stay empty with a warning: to keep the “package” and “cubic metre” units, first create the codes package and cubic_metre in Units of measure. An open request whose lines are all received, rejected or on hold arrives closed. No notification is sent. Attachments are not imported.',
  'purchase-orders':
    'Requires Users, Companies, Company sites and Registries already imported (the supplier is mandatory); it is also better to import Operational sites, Payment methods, VAT rates, Work orders and Purchase requests first. All legacy purchase orders come, also those already turned into a supplier invoice, with lines, cost type, quantity, price, VAT rate, work order and the link to the purchase request line. Amounts and totals are recalculated by QNet. Each order keeps its legacy number; when the company already used it in the same year, it takes the first free number after the highest, with a warning and the legacy number in the notes. A fulfilled order arrives with a receipt of every quantity; a partially fulfilled one arrives without receipts, with a warning. An order with supplier, company, company site or creator not imported fails with the reason. No notification is sent. Attachments, invoice data and due dates are not imported.',
}
