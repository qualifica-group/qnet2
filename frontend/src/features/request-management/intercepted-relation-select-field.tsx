import { useTranslation } from 'react-i18next'
import type {
  Control,
  FieldPath,
  FieldPathValue,
  FieldValues,
} from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useRequestFieldChange } from '@/features/field-change-requests/use-field-change-request-dialog'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Field paths of `TFieldValues` whose value is a nullable relation id.
 * Duplicated (rather than imported) from `relation-select-field.tsx`, which
 * exports no type for it — the constraint itself is a tiny, generic mapped
 * type with no domain knowledge, safe to keep local to this module.
 */
type RelationFieldPath<TFieldValues extends FieldValues> = {
  [K in FieldPath<TFieldValues>]: FieldPathValue<TFieldValues, K> extends number | null ? K : never
}[FieldPath<TFieldValues>]

interface InterceptedRelationSelectFieldProps<
  TFieldValues extends FieldValues,
  TName extends RelationFieldPath<TFieldValues>,
> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key for this field (spec 0004). */
  metaKey: string
  label: string
  hint?: string
  /** `/for-select` resource segment backing the picker. */
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projection for this relation. */
  selected: RelationFieldRef | null
  required?: boolean
  placeholder: string
  emptyLabel: string
  errorLabel: string
  clearLabel: string
  retryLabel: string
  showAvatar?: boolean
  /** The record a proposed change targets (spec 0078). */
  changeRequestSubjectId: number
  /**
   * The `(resource, field)` pair `change_requestable_fields`/`POST
   * /field-change-requests` key on — the backend's own vocabulary, which may
   * differ from this form's RHF `name`/`metaKey`.
   */
  changeRequestResource: string
  changeRequestField: string
  /** i18n KEY of the field's human label (resolved by the dialog itself), e.g. `requestManagement.columns.source`. */
  changeRequestFieldLabelKey: string
}

/** Renders a `{id, name}` relation ref as the `ForSelectItem` shape the picker hydrates from. */
function toForSelectItem(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * Generic field-change-request interception for a single-relation picker
 * (spec 0078 AC-043/044/046): the work-panel counterpart of the grid's
 * `use-table-cell-edit` (E4). Domain-agnostic on purpose — takes
 * `(changeRequestResource, changeRequestField)` as data, never hardcodes a
 * field name — so any module's readonly-but-proposable relation field can
 * reuse it unchanged. Currently lives here because only request-management's
 * "Fonte" needs it; a second caller should move it under
 * `features/field-change-requests/` instead of forking it.
 *
 * - Editable, or readonly with no proposal right → unchanged behaviour:
 *   renders the plain `RelationSelectField` (locked or interactive).
 * - Readonly AND `change_requestable_fields` allows it → the picker STAYS
 *   interactive, but a pick never touches the RHF field (AC-043): it opens
 *   the generic proposal dialog instead, and — since `field.value` never
 *   moved — the trigger falls back to its previous selection on the next
 *   render.
 */
export function InterceptedRelationSelectField<
  TFieldValues extends FieldValues,
  TName extends RelationFieldPath<TFieldValues>,
>({
  control,
  name,
  metaKey,
  label,
  hint,
  resource,
  searchPlaceholder,
  selected,
  required,
  placeholder,
  emptyLabel,
  errorLabel,
  clearLabel,
  retryLabel,
  showAvatar = false,
  changeRequestSubjectId,
  changeRequestResource,
  changeRequestField,
  changeRequestFieldLabelKey,
}: InterceptedRelationSelectFieldProps<TFieldValues, TName>) {
  const { t } = useTranslation()
  const { field: fieldPermission, canRequestChange } = useResourcePermissions()
  const { requestFieldChange } = useRequestFieldChange()
  const permission = fieldPermission(metaKey)
  const intercepted = permission.readonly && canRequestChange(changeRequestField)

  if (!intercepted) {
    return (
      <RelationSelectField
        control={control}
        name={name}
        metaKey={metaKey}
        label={label}
        hint={hint}
        resource={resource}
        searchPlaceholder={searchPlaceholder}
        selected={selected}
        required={required}
        placeholder={placeholder}
        emptyLabel={emptyLabel}
        errorLabel={errorLabel}
        clearLabel={clearLabel}
        retryLabel={retryLabel}
        showAvatar={showAvatar}
      />
    )
  }

  return (
    <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint} required={required}
      description={t('fieldChangeRequests.proposeFromPicker', {
        defaultValue: 'Pick a value to propose a change; it needs approval before it takes effect.',
      })}
    >
      {({ field }) => (
        <FormControl>
          <AsyncPaginatedSelect
            resource={resource}
            value={field.value}
            onChange={() => {}}
            onItemChange={(item) => {
              const nextId = item?.id ?? null
              if (nextId === field.value) {
                return
              }
              requestFieldChange({
                resource: changeRequestResource,
                subjectId: changeRequestSubjectId,
                field: changeRequestField,
                requestedValue: nextId,
                currentLabel: selected?.name ?? null,
                requestedLabel: item?.label ?? null,
                fieldLabelKey: changeRequestFieldLabelKey,
              })
            }}
            selectedItem={toForSelectItem(selected)}
            showAvatar={showAvatar}
            labels={{
              placeholder,
              searchPlaceholder,
              empty: emptyLabel,
              error: errorLabel,
              clearLabel,
              triggerLabel: label,
              retry: retryLabel,
            }}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
