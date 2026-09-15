/**
 * "Enrollee Management" module's own strings (spec 0130): everything else
 * (column labels, actions, dashboard, etc.) is shared with the
 * `requestManagement` namespace, which the two modules reuse identically —
 * see `RequestModuleConfig`/`useRequestModule`. Only the title and the
 * forbidden message are module-specific, since they name the module.
 */

export const enrolleeManagement = {
  title: 'Enrollee Management',
  forbidden: "You don't have permission to view Enrollee Management.",
}
