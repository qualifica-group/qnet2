/**
 * Record actions of the user detail (actions bar: activate/deactivate, sign in
 * as, delete) and the grid's bulk activation (`bulk`, spec 0310). Split out of
 * `en.ts` to stay within the engineering size limits (`.claude/rules/engineering.md`
 * §6); merged into `users.detail.actions`.
 */

export const usersDetailActions = {
  activate: 'Activate',
  activated: 'User activated.',
  stillInactive: 'The user has a termination date in the past and stays inactive.',
  deactivate: 'Deactivate',
  deactivated: 'User deactivated.',
  deactivateConfirm: {
    title: 'Deactivate {{name}}?',
    description: 'The user will no longer be able to sign in until reactivated. Their data stays unchanged.',
  },
  toggleError: 'Unable to update the user status. Please try again.',
  impersonateConfirm: {
    title: 'Sign in as {{name}}?',
    description: "You will use the application with this user's permissions until you go back to your account from the banner at the top.",
  },
  deleteConfirm: {
    title: 'Delete {{name}}?',
    description: 'The user will be permanently deleted. This cannot be undone.',
  },
  deleteInUse: 'The user is the operator of leads or the supervisor of opportunities: reassign them before deleting.',
  bulk: {
    activate: 'Activate selected',
    deactivate: 'Deactivate selected',
    deactivateTitle_one: 'Deactivate {{count}} user?',
    deactivateTitle_other: 'Deactivate {{count}} users?',
    allUpdated_one: '{{count}} user updated.',
    allUpdated_other: '{{count}} users updated.',
    result: '{{updated}} updated, {{failed}} failed.',
    failedItem: '{{name}}: {{message}}',
    error: 'Unable to update the status of the users. Please try again.',
  },
}
