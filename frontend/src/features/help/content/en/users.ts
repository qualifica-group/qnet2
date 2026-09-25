import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'users',
  title: 'Users',
  summary: 'The Users module holds every person who can sign in to qnet, with personal details, sign-in data, roles and employment relationship.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'Every user has a record with personal details, sign-in data, roles and employment relationship. The module is found in **Administration › Users** and only appears to those who have permission to view it.' },
      ],
    },
    {
      id: 'create-user',
      title: 'Creating a user',
      blocks: [
        { type: 'steps', items: ['Open **Administration › Users**.', 'Click **New user**.', 'Fill in the form sections (see the tables below).', 'Click **Save**. The message "User created successfully." appears.'] },
        { type: 'paragraph', text: 'The **Save** button sits both at the top and at the bottom of the form. In the side column, a **Summary** updates as you fill it in.' },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Avatar**', 'Profile picture (JPEG, PNG, GIF or WebP, up to 10 MB).'],
            ['Personal details', 'Identifying data of the person or company.'],
            ['**Email**', "Address used to sign in. It's required."],
            ['**Account password**', 'Initial password, at least 8 characters.'],
            ['**Repeat password**', 'The same password, for confirmation.'],
            ['**Roles**', "One or more roles. The user's permissions come from their roles."],
            ['**Active**', 'If turned off, the account cannot sign in.'],
          ],
        },
        { type: 'tip', text: "If a section doesn't appear, your role does not let you see its fields." },
      ],
    },
    {
      id: 'assignment-configuration',
      title: 'Assignment configuration',
      blocks: [
        { type: 'paragraph', text: 'This section decides which requests can reach the person. A request reaches them only if they belong to the site of the request and have a competency on the requested product category.' },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Competent for all categories**', 'Turn it on if the person follows any product category. Competency rows disappear and are cleared.'],
            ['**Competency**', 'One row for each pair of business function and product category. A parent category also covers its subcategories. Check "All" to cover every category of that function.'],
            ['**Physical site**', "The person's primary operational site."],
            ['**Remote sites**', 'Other operational sites where they work.'],
          ],
        },
        { type: 'paragraph', text: 'Physical site and remote sites count the same way for assignment. At the top of the module a label shows **Assignable** or **Not assignable**.' },
        { type: 'warning', text: 'Without at least one competency and one site, the person receives no assignments. The module flags it with "Missing competency" or "Missing site".' },
      ],
    },
    {
      id: 'profile-and-contract',
      title: 'Profile and employment relationship',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Manager**', 'Turn it on if the person is responsible for other employees.'],
            ['**Job title**', 'Description of the job (up to 255 characters).'],
            ['**Reports to**', 'The direct managers, chosen among users: you can pick more than one. The field is hidden when **Manager** is on.'],
            ['**Employment type**', 'The type of employment relationship.'],
            ['**Company**', 'The reference company.'],
            ['**Qualification**', 'The contractual qualification.'],
            ['**Hired on** / **Terminated on**', 'Start and end dates of the relationship. Termination cannot precede hiring.'],
            ['**Standard daily duration**', 'Expected working time in a day.'],
            ['**Daily break duration**', 'Length of the daily break.'],
          ],
        },
        { type: 'paragraph', text: 'The form also has **Contacts** and **Addresses** sections. At the bottom, an **Other fields** section may appear, with the custom fields for users.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Editing, deactivating and deleting',
      blocks: [
        { type: 'paragraph', text: 'Every row in the list has an actions menu with **View**, **Edit**, **Delete**, **Activity** and **Impersonate**. You only see the actions your role allows.' },
        { type: 'list', items: ['**Edit:** choose **Edit**, change the data and click **Save**.', '**Deactivate:** open **Edit** and turn off **Active**. The person can no longer sign in, but their record and history remain. **Inactive** appears at the top.', '**Delete:** choose **Delete** and confirm.', '**Activity:** shows the history of changes made to the record.'] },
        { type: 'warning', text: 'You cannot delete your own account, nor the last user with the super-admin role.' },
        { type: 'tip', text: 'If a person leaves the company, deactivate them instead of deleting them. This keeps the history of their work.' },
      ],
    },
    {
      id: 'reset-password',
      title: "Resetting a user's password",
      blocks: [
        { type: 'steps', items: ["Open the user's record with **Edit**.", 'In the **Authentication** section, type the **New password** and then **Repeat password**.', 'Click **Save**.'] },
        { type: 'note', text: 'If you leave the password fields empty, the current password stays unchanged. Alternatively, the person can reset it themselves with **Forgot your password?** on the sign-in page.' },
      ],
    },
    {
      id: 'impersonate',
      title: 'Impersonating a user',
      blocks: [
        { type: 'paragraph', text: 'With **Impersonate** you sign into qnet as that user and see exactly what they see. It is used to check permissions or understand a reported issue.' },
        { type: 'steps', items: [
          "In the users list, open the row's actions menu.",
          'Choose **Impersonate**. The dashboard opens.',
          'A banner at the top shows "You are operating as" followed by the user’s name.',
          'To exit, click **Back to your account**.',
        ] },
        { type: 'paragraph', text: 'It requires the **Impersonate** permission on the Users module. You cannot impersonate yourself nor a deactivated user. Only a super-admin can impersonate another super-admin. Every impersonation start and end is logged.' },
        { type: 'warning', text: "While impersonating, every operation is performed on the other user's behalf." },
      ],
    },
  ],
}

export default guide
