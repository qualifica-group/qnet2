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
        { type: 'steps', items: ['Open **Administration › Users** and click **New user**: the form looks like the record, with the same sections and **closed rows**.', 'Click a row (or its pencil) to open it, fill it in and press **Done** to keep the value or **Revert** to put it back (see the tables below). The **Personal details** are required.', '**Contacts** and **Addresses** are filled in the side column and saved together with the user.', 'Click **Save**. The message "User created successfully." appears. If you leave without saving, you are asked to confirm.'] },
        { type: 'paragraph', text: 'The **Save** button sits both at the top and at the bottom of the form. The figures strip at the top (competences, sites, roles, matching) updates as you fill it in.' },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Avatar**', 'Profile picture (JPEG, PNG, GIF or WebP, up to 10 MB).'],
            ['Personal details', 'Identifying data of the person or company.'],
            ['**Email**', "Address used to sign in. It's required."],
            ['**Password**', 'Optional. Leave it empty to send an invite; if you fill in **Password** and **Confirm password**, it is a temporary password of at least 8 characters.'],
            ['**Roles**', "One or more roles. The user's permissions come from their roles."],
            ['**Active**', 'If turned off, the account cannot sign in.'],
          ],
        },
        { type: 'paragraph', text: 'On creation the user automatically receives a welcome email. There are two modes: **without a password** the email contains the link to choose the password; **with a temporary password** the email contains the link to sign in and you tell the user the password yourself (it is never sent by email). In both cases the user gets in right away and sees a welcome window recommending they choose their own password; they can postpone it with **Later**.' },
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
            ['**Assignable**', 'If you turn it off the person receives no assignments at all (Leads, Request Management, Members, import), even with a site and competency. Competency and sites stay configured, ready for when you turn it back on. It is on by default.'],
            ['**Competent for all categories**', 'Turn it on if the person follows any product category. Competency rows disappear and are cleared.'],
            ['**Competency**', 'One row for each pair of business function and product category. A parent category also covers its subcategories. Check "All" to cover every category of that function.'],
            ['**Physical site**', "The person's primary operational site."],
            ['**Remote sites**', 'Other operational sites where they work.'],
          ],
        },
        { type: 'paragraph', text: 'Physical site and remote sites count the same way for assignment. In the figures strip at the top, **Matching** shows **Assignable** or **Not assignable**.' },
        { type: 'note', text: 'An active person (not a service account) must have at least one **Competency** row or **Covers all product categories** turned on: otherwise the save is refused and the error appears on the **Competency** row.' },
        { type: 'warning', text: 'The person receives assignments only when all three conditions hold: **Assignable** on, at least one competency and at least one site. The module flags the missing one with "Assignment turned off", "Missing competency" or "Missing site".' },
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
            ['**Job roles**', 'The job roles the person performs, chosen from the Job roles module list: you can pick several or none.'],
            ['**Reports to**', 'The direct managers, chosen among users: you can pick more than one. The field is hidden when **Manager** is on.'],
            ['**Employment type**', 'The type of employment relationship.'],
            ['**Company**', 'The reference company.'],
            ['**Qualification**', 'The contractual qualification.'],
            ['**Hired on** / **Terminated on**', 'Start and end dates of the relationship. Termination cannot precede hiring.'],
            ['**Standard daily duration**', 'Expected working time in a day.'],
            ['**Daily break duration**', 'Length of the daily break.'],
          ],
        },
        { type: 'paragraph', text: 'The employment sections are there even for a user with no profile yet: the first save creates it. **Contacts** and **Addresses** follow; at the bottom, an **Other fields** section may appear, with the custom fields for users.' },
        { type: 'note', text: 'Turning on **Manager** empties **Reports to**, turning on **Competent for all categories** clears the **Competence** rows: that is why the two rows have no pencil if you cannot also edit the field they empty.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Editing, deactivating and deleting',
      blocks: [
        { type: 'paragraph', text: 'Every row in the list has an actions menu with **View**, **Delete**, **Activity** and **Impersonate**. You only see the actions your role allows.' },
        { type: 'list', items: ['**Edit:** there is no separate edit page. Open the record with **View**, hover the field and press the **pencil** (or click the value), change the value and press **Save**: only that field is saved. **Cancel** (or Esc, or a click outside) closes without saving. Contacts, addresses and the avatar save on their own.', '**Deactivate:** on the record, open the **Active** row and turn it off. The person can no longer sign in, but their record and history remain. **Inactive** appears at the top.', '**Delete:** choose **Delete** and confirm.', '**Activity:** shows the history of changes made to the record.'] },
        { type: 'warning', text: 'You cannot delete your own account, nor the last user with the super-admin role.' },
        { type: 'tip', text: 'If a person leaves the company, deactivate them instead of deleting them. This keeps the history of their work.' },
      ],
    },
    {
      id: 'reset-password',
      title: "Resetting a user's password",
      blocks: [
        { type: 'steps', items: ["Open the user's record with **View**.", 'In the **Credentials & access** section, press the pencil of the **Password** row.', 'Type the **New password** and then **Confirm password**.', 'Click **Save**.'] },
        { type: 'note', text: 'Until you save the row, the current password stays unchanged. A password you set is temporary: at their next sign-in the user will be advised to change it. Alternatively, the person can reset it themselves with **Forgot your password?** on the sign-in page.' },
      ],
    },
    {
      id: 'welcome-email',
      title: 'Welcome email and first sign-in',
      blocks: [
        { type: 'paragraph', text: "Until a user has completed the first sign-in, their record shows the **Awaiting first sign-in** label." },
        { type: 'steps', items: ["Open the user's record.", 'Click **Resend welcome email** (you need the edit permission on users).', 'The message "Welcome email sent." appears.'] },
        { type: 'list', items: ['The link in the email is valid for **72 hours**.', 'Every new send invalidates the previous link.', 'Resending always sends the link to choose the password, even if the user was created with a temporary password.', 'While the user postpones the choice, the label stays and the welcome window reappears at every sign-in.', 'Alternatively the user can use **Forgot your password?** on the sign-in page.'] },
        { type: 'note', text: 'The label and the button disappear once the user chooses their own password.' },
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
