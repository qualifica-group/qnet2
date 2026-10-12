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
    'Without Roles, Companies and Operational sites, role, company and site stay empty with a warning. Each user’s manager is linked automatically at the end of the import. Email, first name and last name are required. A user already in QNet is recognised by email, or by first and last name when it has no legacy id yet: it gets the legacy id and only empty company, site and manager are filled; role, password and contacts stay as in QNet. When several users share the name, the row fails. For new users the password must arrive already hashed (bcrypt). City and province are matched against the QNet list of municipalities.',
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
    'Without Business functions the category has no function, with a warning. The parent category is linked automatically at the end of the import and a category with the same name already in QNet is reused. Categories of the legacy APL branch and those of the “FORMAZIONE OLD” and “APL OLD” functions arrive not selectable.',
  'product-category-attributes':
    'Creates no records: it links the attributes to the categories already imported. Links already in QNet are not changed.',
  'attribute-layouts':
    'A layout using a field not linked to the category fails, so run “Product categories — link attributes” first. An existing layout is never overwritten.',
  products:
    'A product without a category in the legacy goes under the “No categoria” category, to reclassify it from there; a category not imported yet fails the row. Without VAT rates the rate stays empty with a warning. The supplier is set by “Products — link suppliers”.',
  'cost-products':
    'Without VAT rates the rate stays empty with a warning. It creates the Costi category and its sub-categories itself, with no need for Product categories.',
  'email-templates':
    'No prerequisites. The body is cleaned as in the editor, losing images, tables and styles; a name already in use gets a suffix.',
  'document-bundles':
    'No prerequisites. Files are copied within the QNet attachment limits: a missing, too large or disallowed file is skipped with a warning.',
  referents:
    'Without Referent types the type stays empty with a warning. First and last name are required. City and province are matched against the QNet list of municipalities.',
  registries:
    'Without Users, Referents, Sources and Sectors, managers, supervisor, referents, source and sector stay empty with a warning: the registry is created anyway. The sector is matched by name among the imported sectors only. The name is required; placeholder VAT numbers (0, 1, dot) are not imported. No notifications are sent during the import.',
  opportunities:
    'Without its imported registry the row fails: run Registries first. Missing referents, users, source, category and operational site stay empty with a warning. GOL course requests without a customer are not imported.',
  quotes:
    'Without its imported opportunity the row fails. A line without an imported product, for example a discount, is skipped with a warning. Missing VAT rates, payment methods, sites, referents, users and commission recipients stay empty with a warning. An accepted or contracted quote also creates the contract.',
  'work-orders':
    'Without its imported quote the row fails. A quote line already linked to another work order is copied into the quote, with its commissions, and the quote total grows. Notes, documents, stages and tasks are not imported.',
  'work-order-line-payments':
    'Creates no records: it copies the payment status, payment agreement and unpaid amounts of the work order onto each of its lines. Without the imported work order the row fails. Every import rewrites the payment data of the lines.',
  'product-suppliers':
    'Creates no records: it sets the supplier on the imported products. Without the product the row fails; a supplier not yet imported from Registries is skipped with a warning. A supplier already chosen in QNet is never overwritten.',
  'commission-configurations':
    'Each rule needs its recipient (registry, referent or user) and its product or category already imported: when missing, the row fails. A rule on a parent category, such as ISO or GDPR, is imported and applies to all its subcategories.',
}
