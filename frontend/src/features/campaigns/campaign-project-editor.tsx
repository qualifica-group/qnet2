import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import {
  CampaignGeoField,
  CampaignProductLinesField,
  CampaignStatusField,
} from '@/features/campaigns/campaign-fields'
import { CampaignProjectField } from '@/features/campaigns/campaign-project-field'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

interface ProjectEditorProps {
  form: UseFormReturn<CampaignFormValues>
  selected: ForSelectItem | null
}

/**
 * The Project row's editor (spec 0221 D-3/D-4): the picker, whose pick
 * prefills the cascade. Unlinking a campaign that was linked when the row
 * opened empties Stato, Righe and the geo levels (AC-043), which become
 * required again: they are filled right here, so the save never fails on a
 * field out of sight.
 */
export function CampaignProjectEditor({ form, selected }: ProjectEditorProps) {
  const { t } = useTranslation()
  const { control, setValue } = form
  // The editor mounts when the row opens: this is the link it opened with.
  const [linkedAtOpen] = useState(() => form.getValues('project_id') !== null)
  const projectId = useWatch({ control, name: 'project_id' })

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <CampaignProjectField control={control} setValue={setValue} selected={selected} />
      {linkedAtOpen && projectId === null ? (
        <>
          <p className="text-xs text-muted-foreground">{t('campaigns.form.unlinkedHint')}</p>
          <CampaignStatusField control={control} selected={null} />
          <CampaignProductLinesField control={control} />
          <CampaignGeoField control={control} setValue={setValue} />
        </>
      ) : null}
    </div>
  )
}
