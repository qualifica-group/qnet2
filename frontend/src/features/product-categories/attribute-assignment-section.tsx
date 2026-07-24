import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useAttributeCatalog } from '@/features/attributes/use-attribute-catalog'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import { DataTypeBadge, InfoTooltip } from '@/features/product-categories/attribute-assignment-row-controls'
import type {
  AttributeAssignmentInput,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface AttributeAssignmentSectionProps {
  title: string
  description: string
  /** Already filtered to this section's context. */
  assignments: AttributeAssignmentInput[]
  /** Already filtered to this section's context. */
  inherited: ProductCategoryInheritedAttribute[]
  disabled?: boolean
  onAdd: (attributeId: number) => void
  onUpdate: (attributeId: number, patch: Partial<AttributeAssignmentInput>) => void
  onRemove: (attributeId: number) => void
}

/**
 * A single usage-context's attribute list (spec 0061): picker to add, then
 * one row per assigned attribute (data type, `is_required`, `sort_order`,
 * remove), followed by a read-only list of what the context inherits from
 * the category's ancestry. Every non-obvious control carries an info tooltip
 * (task #19). `AttributeAssignmentEditor` renders one of these per context.
 */
export function AttributeAssignmentSection({
  title,
  description,
  assignments,
  inherited,
  disabled,
  onAdd,
  onUpdate,
  onRemove,
}: AttributeAssignmentSectionProps) {
  const { t } = useTranslation()
  const [pickerValue, setPickerValue] = useState<number | null>(null)
  const catalogQuery = useAttributeCatalog()
  const catalog = useMemo(() => catalogQuery.data ?? [], [catalogQuery.data])

  const assignedIds = useMemo(() => new Set(assignments.map((a) => a.attribute_id)), [assignments])
  const pickerOptions = useMemo(
    () =>
      catalog
        .filter((attribute) => !assignedIds.has(attribute.id))
        .map((attribute) => ({ id: attribute.id, name: attribute.name })),
    [catalog, assignedIds],
  )
  const catalogById = useMemo(() => new Map(catalog.map((a) => [a.id, a])), [catalog])

  const handleAdd = (attributeId: number) => {
    onAdd(attributeId)
    setPickerValue(null)
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-surface p-3">
      <div>
        <h4 className="text-sm font-semibold text-foreground">{title}</h4>
        <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
      </div>

      {!disabled && (
        <SearchableSelect
          value={pickerValue}
          onChange={handleAdd}
          options={pickerOptions}
          isPending={catalogQuery.isPending}
          isError={catalogQuery.isError}
          onRetry={() => void catalogQuery.refetch()}
          labels={{
            placeholder: t('productCategories.form.addAttributePlaceholder'),
            searchPlaceholder: t('productCategories.form.attributeSearch'),
            empty: t('productCategories.form.attributeEmpty'),
            noMatch: t('productCategories.form.attributeNoMatch'),
            error: t('productCategories.form.attributeError'),
            retry: t('common.retry'),
          }}
        />
      )}

      {assignments.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('productCategories.form.attributesEmpty')}</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {assignments.map((assignment) => {
            const attribute = catalogById.get(assignment.attribute_id)
            return (
              <li
                key={assignment.attribute_id}
                className="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5"
              >
                <span className="min-w-0 flex-1 truncate text-sm">
                  {attribute?.name ?? `#${assignment.attribute_id}`}
                </span>
                {attribute ? (
                  <DataTypeBadge
                    type={attribute.type}
                    label={t(`customFields.types.${attribute.type}`)}
                    description={t(`customFields.typeInfo.${attribute.type}.desc`)}
                  />
                ) : (
                  <Badge variant="secondary" className="text-xs">
                    —
                  </Badge>
                )}
                <label className="flex items-center gap-1 text-xs text-muted-foreground">
                  <Checkbox
                    checked={assignment.is_required}
                    disabled={disabled}
                    onCheckedChange={(checked) =>
                      onUpdate(assignment.attribute_id, { is_required: checked === true })
                    }
                  />
                  {t('productCategories.form.isRequired')}
                  <InfoTooltip label={t('productCategories.form.isRequiredHelp')} />
                </label>
                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                  <span>{t('productCategories.form.sortOrder')}</span>
                  <InfoTooltip label={t('productCategories.form.sortOrderHelp')} />
                  <Input
                    type="number"
                    className="w-14"
                    aria-label={t('productCategories.form.sortOrder')}
                    value={assignment.sort_order}
                    disabled={disabled}
                    onChange={(event) =>
                      onUpdate(assignment.attribute_id, {
                        sort_order: Number(event.target.value) || 0,
                      })
                    }
                  />
                </div>
                {!disabled && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    aria-label={t('productCategories.form.removeAttribute')}
                    onClick={() => onRemove(assignment.attribute_id)}
                  >
                    <Trash2 aria-hidden="true" />
                  </Button>
                )}
              </li>
            )
          })}
        </ul>
      )}

      {inherited.length > 0 && (
        <div className="mt-1 flex flex-col gap-1.5 border-t pt-3">
          <p className="text-xs font-medium text-muted-foreground">
            {t('productCategories.form.inheritedAttributes')}
          </p>
          <ul className="flex flex-col gap-1.5">
            {inherited.map((attribute) => {
              const InheritedIcon = FIELD_TYPE_ICONS[attribute.type]
              return (
                <li
                  key={attribute.attribute_id}
                  className="flex items-center gap-2 rounded-md bg-muted/50 px-2 py-1.5 text-sm text-muted-foreground"
                >
                  <span className="min-w-0 flex-1 truncate">{attribute.name}</span>
                  <Badge variant="outline" className="gap-1 text-xs">
                    <InheritedIcon className="size-3.5" aria-hidden="true" />
                    {t(`customFields.types.${attribute.type}`)}
                  </Badge>
                  {attribute.is_required && (
                    <Badge variant="outline" className="text-xs">
                      {t('productCategories.form.isRequired')}
                    </Badge>
                  )}
                </li>
              )
            })}
          </ul>
        </div>
      )}

      {catalogQuery.isPending && assignments.length === 0 ? <Skeleton className="h-9 w-full" /> : null}
    </div>
  )
}
