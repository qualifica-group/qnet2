/**
 * Record actions of the user detail (actions bar: activate/deactivate, sign in
 * as, delete). Split out of `en.ts` to stay within the engineering size limits
 * (`.claude/rules/engineering.md` §6); merged into `users.detail.actions`.
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
}
