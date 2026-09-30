import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrations',
  summary: 'The Migrations module imports data from an external system into qnet, usually at startup: roles, users, companies, sites, referents, products.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Administration › Migrations** and is restricted to the **super-admin** role. Each **Source** represents a type of data to import: Roles, Users, Business functions, Companies, Company sites, Operational sites, Referent types, Referents, Sources, Tags, Sectors, VAT rates, Attributes, Product categories, Products, Cost products, Email templates, Document bundles, Task templates and Attribute layouts.' },
        { type: 'note', text: 'The **Cost products** source imports the legacy QNet costs: warehouse articles, vehicles, equipment and expense reports. Each item becomes a product **Usable as cost** (Costs tab of the offer only) under the **Costi** category, in the **Articoli**, **Veicoli**, **Attrezzature** and **Note spese** sub-categories; an article lands in an Articoli sub-category named after its legacy category. License plate, serial number, brand, model, barcode, ministerial code and storage position become product attributes. For articles the cost is the legacy purchase price. Run **VAT rates** first, otherwise the rate stays empty with a warning.' },
        { type: 'note', text: 'The **Email templates** and **Document bundles** sources import the legacy QNet email templates and document bundles, the latter together with all their files. A name already in use gets a numeric suffix; a file that is too large or has a disallowed format is skipped with a warning instead of failing the row.' },
        { type: 'note', text: 'The **Attribute layouts** source imports the Work order sections of all legacy cards (ISO, SOA, Safety, Avvalimenti, GDPR, R&D, PAL, Subsidized finance, Tenders, Site partnerships, Education, GOL Training). The **Processing status** field receives the statuses of the family (root) of the category. A category receives the fields (Work order and Offer) only from the cards of its own products. Run **Attributes**, **Product categories** and **Product categories — link attributes** first: a layout using a field not linked to the category is discarded. An existing layout is never overwritten. The field values of individual work orders are not imported.' },
        { type: 'note', text: 'The **Task templates** source imports the template, its phases, activities and sub-activities. A template whose title contains "non attivo" arrives deactivated. A title already used by another template gets the "(old_id N)" suffix. A sub-activity with an issue (missing parent, parent in another template, a cycle) becomes a top-level activity instead, with a warning in the report.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Step 1: review and preview',
      blocks: [
        { type: 'steps', items: ['Open **Administration › Migrations**.', 'In the **Source** field, choose the type of data to import, for example **Users** or **Products**.', 'The **Expected template** box lists the fields qnet expects from the external system.', 'Click **Show external preview**. Use **Previous** and **Next** to browse the data.'] },
        { type: 'note', text: 'Nothing is created during this step.' },
      ],
    },
    {
      id: 'import-step',
      title: 'Step 2: importing',
      blocks: [
        { type: 'steps', items: ['After checking the preview, click **Import this source**.', 'Read the confirmation window and click **Start import**.', 'Follow the progress. At the end you see the summary: **Total rows**, **Created**, **Skipped** and **Failed**.', 'Check **Warnings and errors** for rows with problems.'] },
        { type: 'tip', text: 'The import keeps running even if you close the window. Records already imported are skipped, so you can repeat the import without creating duplicates.' },
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
