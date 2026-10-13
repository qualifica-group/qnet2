import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MetaField } from '@/features/authorization/MetaField'
import { collectSubtreeIds, findSectorName, flattenSectorTree } from '@/features/sectors/flatten-tree'
import { useSectorTree } from '@/features/sectors/use-sector-tree'
import type { SectorFormValues } from '@/features/sectors/use-sector-form'

/** Sentinel id representing "no parent" in the parent picker (no real sector has id 0). */
const ROOT_PARENT_VALUE = 0

interface SectorParentRowProps {
  inline: InlineEdit
  canEdit: boolean
  /** The sector being edited: its own subtree is not offered as a parent (anti-cycle UX; the backend is authoritative). */
  sectorId?: number
}

/** The parent sector: its name when closed, the searchable sector tree when open. */
export function SectorParentRow({ inline, canEdit, sectorId }: SectorParentRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<SectorFormValues>()
  const parentId = useWatch({ control, name: 'parent_id' })
  const treeQuery = useSectorTree()

  const options = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = sectorId === undefined ? new Set<number>() : collectSubtreeIds(nodes, sectorId)
    return [
      { id: ROOT_PARENT_VALUE, name: t('sectors.form.noParent') },
      ...flattenSectorTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, sectorId, t])

  const parentName = parentId === null ? t('sectors.form.noParent') : findSectorName(treeQuery.data ?? [], parentId)

  return (
    <RecordInlineField
      field="parent_id"
      label={t('sectors.form.parent')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name="parent_id" metaKey="parent_id" label={t('sectors.form.parent')}>
          {({ field, disabled }) => (
            <FormControl>
              <SearchableSelect
                value={field.value ?? ROOT_PARENT_VALUE}
                onChange={(next) => field.onChange(next === ROOT_PARENT_VALUE ? null : next)}
                options={options}
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
      }
    >
      {parentName ?? <DetailEmpty />}
    </RecordInlineField>
  )
}
