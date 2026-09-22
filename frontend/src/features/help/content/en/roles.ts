import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'roles',
  title: 'Roles',
  summary: 'A role is a set of permissions: you assign roles to users and each user receives the permissions of all their roles.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Administration › Roles**. The roles list shows the number of **Permissions** and **Users** for each one.' },
      ],
    },
    {
      id: 'create-edit-role',
      title: 'Creating or editing a role',
      blocks: [
        { type: 'steps', items: ['Open **Administration › Roles**.', 'Click **New role**, or choose **Edit** on a row.', 'In **Role details** type the **Name**. If you want, pick the users right away in **Members**.', 'In the **Permissions** section, choose what the role can do.', 'Click **Save**.'] },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Name**', 'Name of the role, for example the department or job. Required.'],
            ['**Members**', "The users with this role. You can also manage them from each user's record."],
            ['**Permissions**', 'The allowed actions, module by module.'],
          ],
        },
      ],
    },
    {
      id: 'permission-catalog',
      title: 'How the permission catalog is organized',
      blocks: [
        { type: 'paragraph', text: "The **Permissions** section has two panels: on the left a tree that follows the qnet menu, with a **Cross-cutting** area at the bottom for functions present everywhere such as **Notes** and **Attachments**; on the right, the detail of the selected module, with **Actions** and **Fields**." },
        {
          type: 'table',
          headers: ['Action', 'What it allows'],
          rows: [
            ['**View list**', 'Opening the module and seeing its list.'],
            ['**View**', 'Opening a single record.'],
            ['**Create**', 'Adding new records.'],
            ['**Edit**', 'Changing existing records.'],
            ['**Delete**', 'Deleting records.'],
          ],
        },
        { type: 'paragraph', text: 'The other actions sit under **Advanced configuration**, in the **Additional actions** box: for example **Export**, **Import**, **View activity** and **Impersonate**. Some modules have their own actions, such as **Validate**, **Change status** or **View all**.' },
        { type: 'list', items: ['**Search modules or permissions…** filters the tree.', '**Select area** turns on every permission of an area.', '**Select all** turns on every permission of a module.', '**Select all permissions** turns on the whole catalog.', 'Next to each area and module, a counter shows the permissions chosen out of the total.'] },
      ],
    },
    {
      id: 'field-permissions',
      title: 'Field-level permissions',
      blocks: [
        { type: 'paragraph', text: "In the module detail, the **Fields** part controls every field of the record, split into **Native** and **Custom**." },
        {
          type: 'table',
          headers: ['Checkbox', 'Meaning'],
          rows: [
            ['**Visible**', 'The field appears on the record and in the list.'],
            ['**Editable**', 'The field can be changed. If turned off, it stays read-only.'],
            ['**Required**', 'The field must be filled in to save.'],
          ],
        },
        { type: 'note', text: "If you don't touch anything, a field stays visible, editable and not required. Some fields are essential and cannot be restricted. With multiple roles, the widest permission wins: one role that makes the field visible is enough." },
      ],
    },
    {
      id: 'delete-role',
      title: 'Deleting a role',
      blocks: [
        { type: 'paragraph', text: 'Choose **Delete** on the role row and confirm. Users who had it lose the permissions that came from it.' },
      ],
    },
    {
      id: 'super-admin-role',
      title: 'The super-admin role',
      blocks: [
        { type: 'paragraph', text: 'The **super-admin** role is a system role with access to everything. It cannot be edited or deleted.' },
        { type: 'list', items: ['Only a super-admin sees the super-admin role and can assign it.', 'The role cannot be taken away from the last remaining super-admin, nor can it be deleted.', 'Only a super-admin accesses the **Migrations** module.'] },
      ],
    },
    {
      id: 'best-practices',
      title: 'Best practices',
      blocks: [
        { type: 'list', items: [
          '**Grant only the permissions needed.** Everyone should be able to do their job, nothing more.',
          '**Create roles by job**, not by person: "Sales" or "Back office", not "John Smith".',
          '**Use super-admin sparingly.** Reserve it for one or two trusted people.',
          '**Verify with impersonation.** After creating a role, impersonate a user who has it and check what they see.',
          '**Restrict sensitive fields.** Turn off **Visible** for roles that must not see confidential data.',
        ] },
      ],
    },
  ],
}

export default guide
