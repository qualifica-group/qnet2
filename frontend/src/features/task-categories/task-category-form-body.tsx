import { Tags } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { IconPicker } from '@/components/icon-picker'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { TASK_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/task-categories/for-select-api'
import { useTaskCategoryForm } from '@/features/task-categories/use-task-category-form'
import { collectDescendantIds, useTaskCategoryTree } from '@/features/task-categories/use-task-category-tree'
import type { ReactNode } from 'react'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskCategoryTreeNode } from '@/features/task-categories/use-task-category-tree'
import type { TaskCategoryDetail, TaskCategoryFormMode } from '@/features/task-categories/types'

interface TaskCategoryFormBodyProps {
  mode: TaskCategoryFormMode
  onSuccess: (taskCategory: TaskCategoryDetail) => void
  onCancel: () => void
}

/** The metadata keys this form owns, in render order. */
const FIELD_KEYS = ['name', 'description', 'color', 'icon', 'is_active', 'parent_id'] as const

/** Pixels of indent per tree depth level (spec 0154 D-1), same step the task's own category field uses. */
const PARENT_INDENT_STEP_PX = 12

/** Plain (non-badge) indented option row: the admin form pickings a PEER category, not a task lookup. */
function renderIndentedCategoryName(item: ForSelectItem): ReactNode {
  const depth = (item as ForSelectItem & { meta?: { depth?: number } }).meta?.depth ?? 0
  return (
    <span className="min-w-0 truncate" style={{ paddingLeft: depth * PARENT_INDENT_STEP_PX }}>
      {item.label}
    </span>
  )
}

/** Stable module-level defaults: a fresh array/Set per render would break dependency stability. */
const EMPTY_CATEGORY_NODES: TaskCategoryTreeNode[] = []
const EMPTY_EXCLUDED_IDS: ReadonlySet<number> = new Set()

/**
 * The task category create/edit form UI. Every field is wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions` — no hardcoded permission
 * logic lives here. `color` and `icon` reuse the shared `ColorTokenPicker`
 * and `IconPicker` (spec 0101 AC-087), never a bespoke copy.
 * All non-render logic lives in `useTaskCategoryForm`.
 */
export function TaskCategoryFormBody({ mode, onSuccess, onCancel }: TaskCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useTaskCategoryForm({ mode, onSuccess })
  const categoryTree = useTaskCategoryTree()
  const categoryNodes = categoryTree.data ?? EMPTY_CATEGORY_NODES

  const identityVisible = FIELD_KEYS.some((key) => fieldPermission(key).visible)

  // Spec 0154 D-1: a category cannot become its own descendant's parent
  // (cycle). Client-side UX only, self + every descendant excluded when
  // editing; the server's own guard is the actual 422 on `parent_id`.
  const excludedParentIds =
    mode.type === 'edit'
      ? new Set([mode.taskCategory.id, ...collectDescendantIds(categoryNodes, mode.taskCategory.id)])
      : EMPTY_EXCLUDED_IDS
  const persistedParent = mode.type === 'edit' && mode.taskCategory.parent ? mode.taskCategory.parent : null

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Tags}
              title={t('taskCategories.form.sections.identity.title')}
              description={t('taskCategories.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('taskCategories.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('taskCategories.form.description')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('taskCategories.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker
                      value={field.value}
                      onChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="icon"
                metaKey="icon"
                label={t('taskCategories.form.icon')}
              >
                {({ field, disabled, readOnly }) => (
                  <IconPickerControl
                    value={field.value}
                    onChange={field.onChange}
                    disabled={disabled}
                    readOnly={readOnly}
                  />
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('taskCategories.form.isActive')}
                layout="inline"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <RelationSelectField
                control={form.control}
                name="parent_id"
                metaKey="parent_id"
                label={t('taskCategories.form.parentId')}
                hint={t('taskCategories.form.parentIdHint')}
                resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('taskCategories.form.parentIdSearch')}
                selected={persistedParent}
                isItemDisabled={(item) => excludedParentIds.has(item.id)}
                renderItem={renderIndentedCategoryName}
                placeholder={t('taskCategories.form.parentIdPlaceholder')}
                emptyLabel={t('taskCategories.form.parentIdEmpty')}
                errorLabel={t('taskCategories.form.parentIdError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('taskCategories.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('taskCategories.form.saving') : t('taskCategories.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

interface IconPickerControlProps {
  value: string
  onChange: (name: string) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Bridges the shared `IconPicker` into the RHF/`FormItem` context: reads the
 * accessible-error triad ids via `useFormField()` (one level inside the
 * `MetaField` render prop) and supplies the localized picker labels, which are
 * generic UI vocabulary shared with the custom-fields module.
 */
function IconPickerControl({ value, onChange, disabled, readOnly }: IconPickerControlProps) {
  const { t } = useTranslation()
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()

  return (
    <IconPicker
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
      labels={{
        placeholder: t('customFields.form.iconPickerPlaceholder'),
        searchPlaceholder: t('customFields.form.iconSearchPlaceholder'),
        empty: t('customFields.form.iconEmpty'),
        clearLabel: t('customFields.form.iconClear'),
      }}
    />
  )
}
