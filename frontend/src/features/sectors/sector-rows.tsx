import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type UseFormReturn } from 'react-hook-form'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { findInactiveAncestor } from '@/features/sectors/flatten-tree'
import { SectorParentRow } from '@/features/sectors/sector-parent-row'
import { useSectorTree } from '@/features/sectors/use-sector-tree'
import type { SectorFormValues } from '@/features/sectors/use-sector-form'

interface SectorRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/**
 * The active flag. Spec 0212: an inactive ancestor deactivates the whole
 * branch whatever the own switch says, so the row names the ancestor
 * responsible, open or closed.
 */
function SectorActiveRow({ inline, canEdit }: SectorRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<SectorFormValues>()
  const [isActive, parentId] = useWatch({ control, name: ['is_active', 'parent_id'] })
  const treeQuery = useSectorTree()
  const inactiveAncestor = useMemo(
    () => findInactiveAncestor(treeQuery.data ?? [], parentId),
    [treeQuery.data, parentId],
  )
  const notice =
    inactiveAncestor !== null ? (
      <p className="text-xs font-medium text-muted-foreground" role="status">
        {t('sectors.form.isActiveInheritedNotice', { sector: inactiveAncestor.name })}
      </p>
    ) : null

  return (
    <RecordInlineField
      field="is_active"
      label={t('sectors.form.isActive')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField
          control={control}
          name="is_active"
          metaKey="is_active"
          label={t('sectors.form.isActive')}
          layout="inline"
          description={
            <>
              <FormDescription>{t('sectors.form.isActiveHint')}</FormDescription>
              {notice}
            </>
          }
        >
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      }
    >
      <span>{isActive ? t('common.yes') : t('common.no')}</span>
      {notice}
    </RecordInlineField>
  )
}

interface SectorSectionsProps extends SectorRowProps {
  form: UseFormReturn<SectorFormValues>
  /** The sector being edited; absent on a create draft. */
  sectorId?: number
  /** The values the closed custom field rows show: persisted on the detail, the draft's on a create. */
  customFieldValues: Record<string, CustomFieldValue>
}

/** The sector's sections, shared by the detail (saved one row at a time) and the create draft (kept until Salva). */
export function SectorSections({ form, inline, canEdit, sectorId, customFieldValues }: SectorSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('sectors.form.sections.identity.title')} full>
        <RecordFieldList>
          <TextInlineRow<SectorFormValues>
            name="code"
            label={t('sectors.form.code')}
            hint={t('sectors.form.codePlaceholder')}
            inline={inline}
            canEdit={canEdit}
          />
          <TextInlineRow<SectorFormValues> name="name" label={t('sectors.form.name')} inline={inline} canEdit={canEdit} />
          <SectorParentRow inline={inline} canEdit={canEdit} sectorId={sectorId} />
          <SectorActiveRow inline={inline} canEdit={canEdit} />
        </RecordFieldList>
      </RecordSection>
      <CustomFieldRecordSections resource="sectors" values={customFieldValues} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
