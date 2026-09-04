/**
 * The Task module's five configurators (spec 0101, D-4): Statuses, Types,
 * Categories, Priorities and Importances. One sibling file so `en.ts` stays
 * within the engineering size limits (`.claude/rules/engineering.md` §6).
 *
 * The four non-status lookups are IDENTICAL in shape (D-4) — only the entity
 * noun changes — so they are built by `lookupBundle()` rather than copied four
 * times. `taskStatuses` is spelled out: it alone carries
 * `completion_percentage` and the system-row hint (D-5).
 *
 * `columns.*` mirrors each `ColumnCatalog`: it declares `created_at` but NO
 * `updated_at`, while `detail.updated_at` DOES exist because the detail reads
 * the CRUD `data`, which carries both timestamps. Do not "fix" that
 * asymmetry — it reflects the backend.
 *
 * Copy owned by the module author (teammate `frontend-config`) except the page
 * chrome (`forbidden`, `detail.title`/`subtitle`, `form.create*`/`edit*`),
 * which belongs to the pages in `src/pages/task-*-page.tsx`.
 */

/**
 * Per-entity nouns; everything else in a lookup bundle is shared.
 *
 * The `newTask*` key is deliberately NOT built here: a computed `[newKey]`
 * property would collapse the bundle's type into an index signature, and
 * `TranslationResources` would then accept ANY key on the Italian side —
 * silently voiding the compile-time parity this file relies on. Each module
 * spreads it in as a literal below instead.
 */
interface LookupCopy {
  /** Lower-case singular used mid-sentence, e.g. "task type". */
  singular: string
  /** Plural, title case, e.g. "Task Types". */
  plural: string
}

function lookupBundle({ singular, plural }: LookupCopy) {
  const Singular = `${singular.charAt(0).toUpperCase()}${singular.slice(1)}`

  return {
    forbidden: `You don't have permission to view ${plural.toLowerCase()}.`,
    columns: {
      name: 'Name',
      description: 'Description',
      color: 'Color',
      icon: 'Icon',
      sort_order: 'Order',
      is_active: 'Active',
      created_at: 'Created at',
    },
    detail: {
      title: `${Singular} details`,
      subtitle: `Read-only view of the selected ${singular}.`,
      loadError: `Unable to load the ${singular}. Please retry.`,
      description: 'Description',
      color: 'Color',
      icon: 'Icon',
      sort_order: 'Order',
      isActive: 'Active',
      created_at: 'Created at',
      updated_at: 'Updated at',
    },
    form: {
      createTitle: `Create ${singular}`,
      createSubtitle: `Add a new ${singular}.`,
      editTitle: `Edit ${singular}`,
      editSubtitle: `Update the selected ${singular}.`,
      name: 'Name',
      description: 'Description',
      color: 'Color',
      icon: 'Icon',
      isActive: 'Active',
      save: 'Save',
      saving: 'Saving…',
      cancel: 'Cancel',
      created: `${Singular} created successfully.`,
      updated: `${Singular} updated successfully.`,
      deleted: `${Singular} deleted successfully.`,
      nameRequired: 'Name is required.',
      nameMax: 'Name may contain at most 191 characters.',
      descriptionMax: 'Description may contain at most 500 characters.',
      colorRequired: 'Color is required.',
      colorInvalid: 'Pick a color from the palette.',
      iconInvalid: 'Pick an icon from the catalogue.',
      genericError: 'Something went wrong. Please retry.',
      deleteError: `Unable to delete the ${singular}. Please retry.`,
      deleteForbidden: `You cannot delete this ${singular}.`,
      // Fallback only: on a 409 the module shows the backend message verbatim,
      // which names the resource. This shows only if the body carries none.
      deleteInUse: `This ${singular} is used by a task and cannot be deleted.`,
      sections: {
        identity: {
          title: 'Details',
          description: 'Name, description, color, icon and status.',
        },
      },
    },
    reorder: {
      openButton: 'Reorder',
      title: `Reorder ${plural}`,
      // No mention of rows pinned first or last: unlike task statuses, these
      // lookups have no system rows, so every row is freely reorderable.
      subtitle:
        'Drag the rows to change their order. The order applies to the table and to every dropdown.',
      dragHandleLabel: 'Drag to reorder',
      loadError: 'Unable to load the list. Please retry.',
      saved: 'Order updated successfully.',
      forbidden: 'You cannot reorder these rows.',
      genericError: 'Unable to update the order. Please retry.',
      // The reorder sheet also lists rows an admin deactivated — it must,
      // since the endpoint validates `ordered_ids` against the COMPLETE
      // set and omitting them would 422 on every drag. This badge is the
      // only thing telling the admin why a row missing from every
      // dropdown still shows up here.
      inactiveBadge: 'Inactive',
    },
  }
}

const types = lookupBundle({ singular: 'task type', plural: 'Task Types' })
export const taskTypes = {
  ...types,
  form: { ...types.form, newTaskType: 'New task type' },
}

const categories = lookupBundle({ singular: 'task category', plural: 'Task Categories' })
export const taskCategories = {
  ...categories,
  form: { ...categories.form, newTaskCategory: 'New task category' },
}

const priorities = lookupBundle({ singular: 'task priority', plural: 'Task Priorities' })
export const taskPriorities = {
  ...priorities,
  form: { ...priorities.form, newTaskPriority: 'New task priority' },
}

const importances = lookupBundle({ singular: 'task importance', plural: 'Task Importances' })
export const taskImportances = {
  ...importances,
  form: { ...importances.form, newTaskImportance: 'New task importance' },
}

export const taskStatuses = {
  forbidden: "You don't have permission to view task statuses.",
  columns: {
    name: 'Name',
    description: 'Description',
    color: 'Color',
    icon: 'Icon',
    sort_order: 'Order',
    group: 'Phase',
    is_active: 'Active',
    completion_percentage: 'Completion',
    created_at: 'Created at',
  },
  detail: {
    title: 'Task status details',
    subtitle: 'Read-only view of the selected task status.',
    loadError: 'Unable to load the task status. Please retry.',
    description: 'Description',
    color: 'Color',
    icon: 'Icon',
    sort_order: 'Order',
    group: 'Phase',
    isActive: 'Active',
    completionPercentage: 'Completion percentage',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    createTitle: 'Create task status',
    createSubtitle: 'Add a new task status.',
    editTitle: 'Edit task status',
    editSubtitle: 'Update the selected task status.',
    newTaskStatus: 'New status',
    name: 'Name',
    description: 'Description',
    color: 'Color',
    icon: 'Icon',
    group: {
      label: 'Phase',
      open: 'Open',
      pending: 'Pending',
      in_validation: 'To validate',
      closed_positive: 'Closed (positive outcome)',
      closed_negative: 'Closed (negative outcome)',
    },
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Task status created successfully.',
    updated: 'Task status updated successfully.',
    deleted: 'Task status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name may contain at most 191 characters.',
    descriptionMax: 'Description may contain at most 500 characters.',
    colorRequired: 'Color is required.',
    colorInvalid: 'Pick a color from the palette.',
    iconInvalid: 'Pick an icon from the catalogue.',
    genericError: 'Something went wrong. Please retry.',
    deleteError: 'Unable to delete the task status. Please retry.',
    deleteForbidden: 'You cannot delete this task status.',
    deleteInUse: 'This task status is used by a task and cannot be deleted.',
    completionPercentage: 'Completion percentage',
    completionPercentageRequired: 'Completion percentage is required.',
    completionPercentageInvalid: 'Completion percentage must be a whole number.',
    completionPercentageRange: 'Completion percentage must be between 0 and 100.',
    hints: {
      // Deliberately names FOUR fields: the backend message still says "only
      // name and color" and was left untouched to preserve AC-048, but the
      // module never shows it. This is the accurate wording.
      systemStatusFields:
        'A system status has fixed fields — only name, color, icon and completion percentage can be changed.',
    },
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description, color, icon, phase and status.',
      },
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder Task Statuses',
    // Verified against `TaskStatus::SYSTEM_HEAD_KEYS` (open) and
    // `SYSTEM_TAIL_KEYS` (closed_positive, closed_negative): one pinned first,
    // two pinned last (AC-047). The former head keys in_progress/pending/
    // in_validation are phases now, carried by `group`, not system rows.
    subtitle:
      'Drag the custom statuses to reorder them. The three system statuses stay pinned at the top and at the bottom.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the list. Please retry.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these rows.',
    genericError: 'Unable to update the order. Please retry.',
    inactiveBadge: 'Inactive',
  },
}
