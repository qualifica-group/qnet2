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
        { type: 'paragraph', text: 'The module is found in **Administration › Migrations** and is restricted to the **super-admin** role. Each **Source** represents a type of data to import: Roles, Users, Business functions, Companies, Company sites, Operational sites, Referent types, Referents, Sources, Tags, Sectors, VAT rates, Attributes, Product categories, Products and Task templates.' },
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
