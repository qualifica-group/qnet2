import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { TASK_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/task-categories/for-select-api'
import { collectDescendantIds, useTaskCategoryTree } from '@/features/task-categories/use-task-category-tree'
import type { TaskCategoryTreeNode } from '@/features/task-categories/use-task-category-tree'
import type { TaskCategoryDetail } from '@/features/task-categories/types'
import type { TaskCategoryFormValues } from '@/features/task-categories/use-task-category-form'

/** Pixels of indent per tree depth level (spec 0154 D-1), same step the task's own category field uses. */
const PARENT_INDENT_STEP_PX = 12

/** Stable module-level defaults: a fresh array/Set per render would break dependency stability. */
const EMPTY_CATEGORY_NODES: TaskCategoryTreeNode[] = []
const EMPTY_EXCLUDED_IDS: ReadonlySet<number> = new Set()

/** Plain (non-badge) indented option row: the admin form picks a PEER category, not a task lookup. */
function renderIndentedCategoryName(item: ForSelectItem): ReactNode {
  const depth = (item as ForSelectItem & { meta?: { depth?: number } }).meta?.depth ?? 0
  return (
    <span className="min-w-0 truncate" style={{ paddingLeft: depth * PARENT_INDENT_STEP_PX }}>
      {item.label}
    </span>
  )
}

interface TaskCategoryParentRowProps {
  inline: InlineEdit
  canEdit: boolean
  /** The saved category on a detail; absent on the create draft. */
  taskCategory?: TaskCategoryDetail
}

/**
 * The parent category row (spec 0154 D-1): the tree picker when open, the
 * parent's name when closed. A pick is remembered by id, so the closed value
 * of a draft (which only holds `parent_id`) survives "Ripristina".
 */
export function TaskCategoryParentRow({ inline, canEdit, taskCategory }: TaskCategoryParentRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<TaskCategoryFormValues>()
  const parentId = useWatch({ control, name: 'parent_id' })
  const [pickedNames, setPickedNames] = useState<Record<number, string>>({})
  const categoryNodes = useTaskCategoryTree().data ?? EMPTY_CATEGORY_NODES

  // A category cannot become its own descendant's parent (cycle). Client-side
  // UX only; the server's own guard is the actual 422 on `parent_id`.
  const excludedParentIds = taskCategory
    ? new Set([taskCategory.id, ...collectDescendantIds(categoryNodes, taskCategory.id)])
    : EMPTY_EXCLUDED_IDS
  const persistedParent = taskCategory?.parent ?? null
  const parentName =
    parentId === null
      ? null
      : (pickedNames[parentId] ?? (persistedParent?.id === parentId ? persistedParent.name : null))

  const rememberPick = (item: ForSelectItem | null) => {
    if (item) {
      setPickedNames((names) => ({ ...names, [item.id]: item.label }))
    }
  }

  return (
    <RecordInlineField
      field="parent_id"
      label={t('taskCategories.form.parentId')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="parent_id"
          metaKey="parent_id"
          label={t('taskCategories.form.parentId')}
          hint={t('taskCategories.form.parentIdHint')}
          resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('taskCategories.form.parentIdSearch')}
          selected={persistedParent}
          isItemDisabled={(item) => excludedParentIds.has(item.id)}
          onItemChange={rememberPick}
          renderItem={renderIndentedCategoryName}
          placeholder={t('taskCategories.form.parentIdPlaceholder')}
          emptyLabel={t('taskCategories.form.parentIdEmpty')}
          errorLabel={t('taskCategories.form.parentIdError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {parentName ?? <DetailEmpty />}
    </RecordInlineField>
  )
}
