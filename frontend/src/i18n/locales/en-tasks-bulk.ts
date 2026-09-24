/**
 * Task list bulk actions (spec 0156 D-6, `POST /api/tasks/bulk`). Split out
 * of `en-tasks.ts` purely to stay under the engineering.md §6 size budget;
 * merged back as `tasks.bulk` in `en.ts`.
 */
export const tasksBulk = {
  assign: 'Assign',
  complete: 'Complete',
  uncomplete: 'Reopen',
  uncompleteConfirmDescription_one: 'The selected task moves back to "In progress".',
  uncompleteConfirmDescription_other: 'The {{count}} selected tasks move back to "In progress".',
  block: 'Block',
  blockConfirmDescription_one: 'The selected task will be blocked.',
  blockConfirmDescription_other: 'The {{count}} selected tasks will be blocked.',
  unblock: 'Unblock',
  unblockConfirmDescription_one: 'The selected task will be unblocked.',
  unblockConfirmDescription_other: 'The {{count}} selected tasks will be unblocked.',
  priority: 'Priority',
  startDate: 'Start date',
  endDate: 'Due date',
  delete: 'Delete',
  deleteConfirmDescription_one: 'The selected task will be deleted. This action cannot be undone.',
  deleteConfirmDescription_other: 'The {{count}} selected tasks will be deleted. This action cannot be undone.',
  success_one: 'Task updated.',
  success_other: '{{count}} tasks updated.',
  forbidden: "You don't have permission for this bulk action.",
  genericError: 'Something went wrong with the bulk action. Please try again.',
  incompatibleError_one: 'One task is not compatible with this action: nothing was changed.',
  incompatibleError_other: '{{count}} tasks are not compatible with this action: nothing was changed.',
  incompatibleReason: 'Task #{{id}}: {{reason}}',
  assignDialog: {
    title_one: 'Assign the selected task',
    title_other: 'Assign {{count}} tasks',
    assigneesRequired: 'Select at least one assignee.',
    confirm: 'Assign',
  },
  completeDialog: {
    title_one: 'Complete the selected task',
    title_other: 'Complete {{count}} tasks',
    confirm: 'Complete',
  },
  dateDialog: {
    title: '{{field}} — {{count}} tasks',
    dateRequired: 'The date is required.',
    confirm: 'Save',
  },
  priorityDialog: {
    title_one: 'Priority of the selected task',
    title_other: 'Priority of {{count}} tasks',
    priorityRequired: 'Choose a priority.',
    confirm: 'Save',
  },
}
