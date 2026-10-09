import { useMemo } from 'react'
import { useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ListTree } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl, FormDescription } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useSectorTree } from '@/features/sectors/use-sector-tree'
import {
  collectSubtreeIds,
  findInactiveAncestor,
  flattenSectorTree,
} from '@/features/sectors/flatten-tree'
import { useSectorForm } from '@/features/sectors/use-sector-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { SectorDetail, SectorFormMode } from '@/features/sectors/types'

interface SectorFormBodyProps {
  mode: SectorFormMode
  onSuccess: (sector: SectorDetail) => void
  onCancel: () => void
}

/** Sentinel id representing "no parent" in the parent picker (no real sector has id 0). */
const ROOT_PARENT_VALUE = 0

/**
 * The sector create/edit form UI: `name`, `parent_id` and `is_active`, wrapped in
 * `MetaField` (spec 0004). All non-render logic lives in `useSectorForm`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero sectors-specific rendering/validation logic.
 */
export function SectorFormBody({ mode, onSuccess, onCancel }: SectorFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useSectorForm({ mode, onSuccess })
  const treeQuery = useSectorTree()

  const parentOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = mode.type === 'edit' ? collectSubtreeIds(nodes, mode.sector.id) : new Set<number>()
    return [
      { id: ROOT_PARENT_VALUE, name: t('sectors.form.noParent') },
      ...flattenSectorTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, mode, t])

  // Spec 0212: an inactive ancestor deactivates the whole branch whatever the
  // own switch says, so the form names the ancestor responsible.
  const parentId = useWatch({ control: form.control, name: 'parent_id' })
  const inactiveAncestor = useMemo(
    () => findInactiveAncestor(treeQuery.data ?? [], parentId),
    [treeQuery.data, parentId],
  )

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('parent_id').visible ||
    fieldPermission('is_active').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={ListTree}
              title={t('sectors.form.sections.identity.title')}
              description={t('sectors.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('sectors.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="parent_id"
                metaKey="parent_id"
                label={t('sectors.form.parent')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <SearchableSelect
                      value={field.value ?? ROOT_PARENT_VALUE}
                      onChange={(next) =>
                        field.onChange(next === ROOT_PARENT_VALUE ? null : next)
                      }
                      options={parentOptions}
                      isPending={treeQuery.isPending}
                      isError={treeQuery.isError}
                      onRetry={() => void treeQuery.refetch()}
                      disabled={disabled}
                      labels={{
                        placeholder: t('sectors.form.parentPlaceholder'),
                        searchPlaceholder: t('sectors.form.parentSearch'),
                        empty: t('sectors.form.parentEmpty'),
                        noMatch: t('sectors.form.parentNoMatch'),
                        error: t('sectors.form.parentError'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('sectors.form.isActive')}
                layout="inline"
                description={
                  <>
                    <FormDescription>{t('sectors.form.isActiveHint')}</FormDescription>
                    {inactiveAncestor !== null ? (
                      <p className="text-xs font-medium text-muted-foreground" role="status">
                        {t('sectors.form.isActiveInheritedNotice', { sector: inactiveAncestor.name })}
                      </p>
                    ) : null}
                  </>
                }
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
            </FormSection>
          )}

          <CustomFieldsSection resource="sectors" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('sectors.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('sectors.form.saving') : t('sectors.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
