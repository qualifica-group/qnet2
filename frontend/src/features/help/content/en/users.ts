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
        { type: 'paragraph', text: 'Every user has a record with personal details, sign-in data, roles and employment relationship. The module is found in **Personnel › Users** and only appears to those who have permission to view it.' },
      ],
    },
    {
      id: 'create-user',
      title: 'Creating a user',
      blocks: [
        { type: 'steps', items: ['Open **Personnel › Users** and click **New user**: the form looks like the record, with the same sections and **closed rows**.', 'Click a row (or its pencil) to open it, fill it in and press **Done** to keep the value or **Revert** to put it back (see the tables below). The **Personal details** are required.', '**Contacts** and **Addresses** are filled in the side column and saved together with the user.', 'Click **Save**. The message "User created successfully." appears. If you leave without saving, you are asked to confirm.'] },
        { type: 'paragraph', text: 'The **Save** button sits both at the top and at the bottom of the form. The figures strip at the top (assignment scopes, sites, roles, matching) updates as you fill it in.' },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Avatar**', 'Profile picture (JPEG, PNG, GIF or WebP, up to 10 MB).'],
            ['Personal details', 'Identifying data of the person or company.'],
            ['**Email**', "Address used to sign in. It's required."],
            ['**Password**', 'Not filled in: the system generates it and sends it by email (the form row says so).'],
            ['**Roles**', "One or more roles. The user's permissions come from their roles."],
            ['**Active**', 'If turned off, the account cannot sign in.'],
          ],
        },
        { type: 'paragraph', text: 'On creation the system **generates a 12-character password** and sends it to the user in the welcome email, together with a **Sign in** button. You do not have to tell it to them. At first sign-in the user gets in and sees a window asking them to choose a new password: until they save it they cannot use QNet (they can only **Sign out**).' },
        { type: 'tip', text: "If a section doesn't appear, your role does not let you see its fields." },
      ],
    },
    {
      id: 'assignment-configuration',
      title: 'Assignment configuration',
      blocks: [
        { type: 'paragraph', text: 'This section decides which requests can reach the person. A request reaches them only if they belong to the site of the request and have an assignment scope on the requested product category.' },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Assignable**', 'If you turn it off the person receives no assignments at all (Leads, Request Management, Members, import), even with a site and an assignment scope. Assignment scope and sites stay configured, ready for when you turn it back on. It is on by default.'],
            ['**Scope on all categories**', 'Turn it on if the person follows any product category. Assignment scope rows disappear and are cleared.'],
            ['**Assignment scope**', 'One row for each pair of business function and product category. A parent category also covers its subcategories. Check "All" to cover every category of that function: **New** and **Old** appear next to it (Old = the legacy [OLD] categories migrated from the old QNet), both checked; clear one to limit the row to the new categories only or to the old ones only (at least one stays checked). It applies both to assignments and to the records visible with "View by competence". On the same function you can place a row on a specific category beside the "All" row, or a child category beside its parent. Each row has a **percentage** (whole number from 1 to 100): how much of the person’s commitment goes to that scope, for example 50% on one category, 20% on another and 30% on a third. Under the rows a colored bar shows the split (each row has its own color, marked by the dot beside the percentage) and the total says how much is missing or over 100%: with at least one row it must be exactly 100%. In the detail each row shows its percentage with a small bar. "Add product line" proposes the percentage still missing to 100%.'],
            ['**Physical site**', "The person's primary operational site."],
            ['**Remote sites**', 'Other operational sites where they work.'],
          ],
        },
        { type: 'paragraph', text: 'Physical site and remote sites count the same way for assignment. In the figures strip at the top, **Matching** shows **Assignable** or **Not assignable**.' },
        { type: 'note', text: 'The **Assignment scopes** are optional and separate from membership: they only drive assignments. The percentage is informative only: it changes neither who receives assignments nor the fair distribution. With **Scope on all categories** there are no rows and no percentage to enter. The requirement for an active person (not a service account) applies to the **Business functions of membership** (see **Profile and employment relationship**).' },
        { type: 'warning', text: 'The person receives assignments only when all three conditions hold: **Assignable** on, at least one assignment scope and at least one site. The module flags the missing one with "Assignment turned off", "Missing assignment scope" or "Missing site".' },
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
            ['**Job roles**', 'The job roles the person performs, chosen from the Job roles module list: you can pick several or none. On the record, clicking a job role opens its record with the list of activities (if you can view the Job roles module).'],
            ['**Business functions of membership**', 'The business functions the person belongs to, one or more. It is the same data as the function\'s **Associated users**: changing it here or there is equivalent. It is required for an active person (not a service account): otherwise the save is refused and the error appears under the field. It is not the same as the **Assignment scopes**, which stay optional.'],
            ['**Manager of functions**', 'The functions the person manages, more than one allowed. It is edited in place if you can edit both the user and the business functions (otherwise the row stays read only). A function that had another manager passes to this person, replacing them; a function removed from the list is left without a manager. The same data can also be changed from the business function record. When there are none it reads "Not the manager of any function.".'],
            ['**Reports to**', 'The direct managers, chosen among users: you can pick more than one. The field is always there, also for someone who manages a function.'],
            ['**Employment type**, **Company**, **Contract type**, **Working time**, **Contract expiry**, **In force since**', 'Read only: they show the contract condition in force today. Change them from the **Contract history** (see below).'],
            ['**Hired on** / **Terminated on**', 'Start and end dates of the relationship. Termination cannot precede hiring.'],
            ['**Standard daily duration**', 'Expected working time in a day.'],
            ['**Daily break duration**', 'Length of the daily break.'],
          ],
        },
        { type: 'paragraph', text: 'The employment sections are there even for a user with no profile yet: the first save creates it. **Contacts** and **Addresses** follow; at the bottom, an **Other fields** section may appear, with the custom fields for users.' },
        { type: 'note', text: 'Turning on **Scope on all categories** clears the **Assignment scope** rows: that is why the two rows have no pencil if you cannot also edit the field they empty.' },
        { type: 'paragraph', text: 'Under the name, in the card header, the business functions of membership appear as tags, with a crown on those the person manages; the managed functions the person is not a member of follow. In the users list the **Manager of functions** column (hidden by default, filterable by function) replaces the old **Manager** column.' },
      ],
    },
    {
      id: 'contract-history',
      title: 'Contract history',
      blocks: [
        { type: 'paragraph', text: 'On the card of an already saved user, the **Contract history** section lists the person’s contract conditions, newest first: start, end, employment type, contract type, working time, contract expiry, company and, if you have the permission, the salary. The one in force today has the **Current** label. When creating a user the section says the history is filled in after saving.' },
        { type: 'steps', items: ['Click **New condition**.', 'Enter the **Start date** (a date not yet used for that person), the **Relationship type** (Employee, Self-employed, Intern or Other), the **Contract type** (required when the relationship type is Employee; chosen among the active ones of the **Contract types** module), the **Working time** (Full time or Part time, required for an Employee), the **Contract expiry** if there is one and the **Company**.', 'If you have the compensation permission, also enter **Salary** and **Period** (month, year, hour): they go together.', 'Add a **Reason** if needed and click **Save**.'] },
        { type: 'list', items: ['Conditions are never edited: every change is a new condition. The end of a condition is the day before the next one starts.', 'To fix a mistake use **Delete last condition** (shown only on the last one) and enter a new one. Deleting a condition that is not the last is not possible.', 'A condition starting in the future does not change the card’s **Employment type**, **Contract type**, **Working time**, **Contract expiry** and **Company** until that date arrives.', 'The **Contract expiry** is optional and cannot come before the start date. It is information only: on that date the condition does not close and the person is not terminated. It differs from the end of the condition, which is the day before the next one starts.', 'Conditions entered before contract type, working time and expiry were introduced stay without them: a new condition is needed to set them.', 'The actions require the permission to edit users and the **Contract history** field editable in your role (see “Who sees the contract data”).'] },
        { type: 'note', text: 'If the start date is already used for that person, the form reports it on the field.' },
      ],
    },
    {
      id: 'equipment',
      title: 'Equipment',
      blocks: [{ type: 'paragraph', text: 'With the **View list** permission on Equipment, the record of an already saved user shows the **Equipment** section with the physical goods they hold (product, site, quantity). With the **Manage** permission each row has **Return**: after confirmation it records the return of the whole quantity to the site. Without the permission the section does not appear.' }],
    },
    {
      id: 'payment',
      title: 'Payment and compensation data',
      blocks: [
        { type: 'paragraph', text: 'The **Payment** section holds the **IBAN** the person is paid on and the **Account holder**; the IBAN is read in groups of four characters and edited in place like the other fields. An invalid IBAN shows an error on the field.' },
        { type: 'paragraph', text: 'The same section shows the **Hourly cost**, read-only: it is not entered by hand but derived from the salary of the contract condition in force today. An hourly salary counts as is, a monthly one is divided by 173 hours, a yearly one by 2,076 hours (173 × 12); the result is rounded to the cent. It is the gross salary, with no social contributions or severance pay. With no salary in the condition in force the hourly cost stays empty.' },
        { type: 'warning', text: 'IBAN and salary are compensation data: you see and edit them only with the **Manage compensation data** permission (users.manageCompensation), held by the super-admin, the admin and the **Risorse Umane** (HR) role. Without it the **Payment** section (with the hourly cost) and the salary fields do not appear.' },
      ],
    },
    {
      id: 'contract-visibility',
      title: 'Who sees the contract data',
      blocks: [
        { type: 'paragraph', text: 'The **Contract**, **Contract history**, **Contract data** and **Payment** sections hold sensitive data. Who sees them is decided by the role’s **field permissions** (Roles form, Users fields): a hidden field does not appear on the card, and the section disappears when none of its fields is left visible.' },
        { type: 'list', items: ['The **Risorse Umane** (HR office) role sees and edits everything: staff records, contract history, payment, contract types, job roles, activities, business functions, org chart, everyone’s absences and absence types, courses and everyone’s trainings, the skills catalogue and everyone’s skills; it also reads everyone’s time entries and the companies. It cannot change a user’s roles nor sign in as another user.', 'The **admin** and the **super-admin** see everything.', 'The other roles of the installation (supervisor, coordinator, marketing, sales, teaching supervisor, sales back office, base operator) do not see the contract data, not even in the Users grid columns (Company, Relationship type, Contract type, Working time, Contract expiry, Status, Hired at, Terminated at) nor in the export.', 'Someone with several roles sees a field when at least one of their roles shows it.'] },
        { type: 'note', text: 'Whoever cannot see the employment dates and durations can still save the card: the values they cannot see stay as they are.' },
        { type: 'warning', text: 'A new role created from the Roles form, with no field permissions, sees everything: if it must see users but not the contract data, hide in its form the Contract, Contract history (employment.contracts), Contract data and Payment fields.' },
      ],
    },
    {
      id: 'work-contacts',
      title: 'Work contacts',
      blocks: [
        { type: 'paragraph', text: 'In a user’s **Contacts**, when you add or edit a contact you can tick **Work contact**. Work contacts show the **Work** label.' },
      ],
    },
    {
      id: 'termination',
      title: 'Termination',
      blocks: [
        { type: 'paragraph', text: 'When the **Terminated at** date arrives (or has already passed when saving) the person is deactivated and can no longer sign in; the card and history stay on file. If you remove the date the person is not reactivated automatically: turn **Active** back on by hand.' },
        { type: 'paragraph', text: 'In the users list the **Status** column shows **Employed** or **Terminated** and can be filtered.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Editing, deactivating and deleting',
      blocks: [
        { type: 'paragraph', text: 'Every row in the list has an actions menu with **View**, **Delete**, **Activity** and **Impersonate**. You only see the actions your role allows.' },
        { type: 'paragraph', text: 'On the record, under the name, there is the actions bar: **Send welcome email** (for any active user other than yourself), **Deactivate** or **Activate**, **Impersonate** and **Delete**. Only the actions possible for that user and allowed by your role appear; on your own account you find none of **Send welcome email**, **Deactivate**, **Impersonate** or **Delete**.' },
        { type: 'list', items: ['**Edit:** there is no separate edit page. Open the record with **View**, hover the field and press the **pencil** (or click the value), change the value and press **Save**: only that field is saved. **Cancel** (or Esc, or a click outside) closes without saving. Contacts, addresses and the avatar save on their own.', '**Deactivate:** on the record, press **Deactivate** and confirm (or open the **Active** row and turn it off). The person can no longer sign in, but their record and history remain. **Inactive** appears at the top.', '**Reactivate:** press **Activate**. If the **Terminated at** date has already passed the person stays inactive and a warning appears: remove the date first. An active person must belong to at least one business function, otherwise the activation is refused with the reason.', '**Activate or deactivate several users at once:** in the list tick the rows, open **Actions** and choose **Activate selected** or **Deactivate selected** (deactivation asks for confirmation). The same rules as the record apply, user by user: whoever cannot be changed (your own account when deactivating, a termination date already passed, no business function, an **Active** field your role cannot edit) stays as it was, the others are updated and a single message reports who failed and why. It requires the edit permission on users.', '**Delete:** choose **Delete** (from the row menu or the record) and confirm. From the record, after deleting you go back to the list.', '**Activity:** shows the history of changes made to the record.'] },
        { type: 'warning', text: 'A user with the super-admin role can be edited, deactivated or deleted only by another super-admin: for everyone else their record is read-only. You cannot delete your own account, nor the last user with the super-admin role. Nor can you delete a user who is the operator of leads or the supervisor of opportunities: reassign them first.' },
        { type: 'tip', text: 'If a person leaves the company, deactivate them instead of deleting them. This keeps the history of their work.' },
      ],
    },
    {
      id: 'reset-password',
      title: "Resetting a user's password",
      blocks: [
        { type: 'steps', items: ["Open the user's record with **View**.", 'In the **Credentials & access** section, press the pencil of the **Password** row.', 'Type the **New password** and then **Confirm password**.', 'Click **Save**.'] },
        { type: 'note', text: 'Until you save the row, the current password stays unchanged. A password you set is temporary: at their next sign-in the user will have to choose a new one. Alternatively, the person can reset it themselves with **Forgot your password?** on the sign-in page, or you can use **Send welcome email** (see **Welcome email and first sign-in**).' },
      ],
    },
    {
      id: 'welcome-email',
      title: 'Welcome email and first sign-in',
      blocks: [
        { type: 'paragraph', text: "Until a user has completed the first sign-in, their record shows the **Awaiting first sign-in** label." },
        { type: 'steps', items: ["Open the user's record.", 'In the actions bar under the name, click **Send welcome email** (you need the edit permission on users; the user must be active and not yourself).', 'Confirm: a new password is generated and sent to the user\'s email.', 'The message "Welcome email sent." appears.'] },
        { type: 'list', items: ['The previous password stops working and the user\'s open sessions are closed.', 'Every new send makes the user choose their own password again at first sign-in.', 'Users imported from the old QNet must change their password at first sign-in.', 'Alternatively the user can use **Forgot your password?** on the sign-in page.'] },
        { type: 'note', text: 'The **Awaiting first sign-in** label disappears once the user chooses their own password; the button stays available for resends.' },
      ],
    },
    {
      id: 'impersonate',
      title: 'Impersonating a user',
      blocks: [
        { type: 'paragraph', text: 'With **Impersonate** you sign into qnet as that user and see exactly what they see. It is used to check permissions or understand a reported issue.' },
        { type: 'steps', items: [
          "In the users list, open the row's actions menu, or open the user's record.",
          'Choose **Impersonate** and confirm. The dashboard opens.',
          'A banner at the top shows "You are operating as" followed by the user’s name.',
          'To exit, click **Back to your account**.',
        ] },
        { type: 'paragraph', text: 'It requires the **Impersonate** permission on the Users module. You cannot impersonate yourself nor a deactivated user. Only a super-admin can impersonate another super-admin. Every impersonation start and end is logged in the impersonated user’s **Activity history**, as **Impersonation started** and **Impersonation stopped**, with the name of who impersonated.' },
        { type: 'warning', text: "While impersonating, every operation is performed on the other user's behalf." },
      ],
    },
  ],
}

export default guide
