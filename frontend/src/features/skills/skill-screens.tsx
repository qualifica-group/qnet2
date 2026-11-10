/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { skillKeys, fetchSkill } from '@/features/skills/api'
import { SkillForm } from '@/features/skills/skill-form'
import { SkillDetailView } from '@/features/skills/skill-detail'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'
import { type Skill } from '@/features/skills/types'

/** Content-only `skills` screens for the module registry (spec 0042). */
export function SkillDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: skill,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(skillKeys.detail(id), () => fetchSkill(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('skills.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !skill) {
    return <DetailLoading />
  }

  return <SkillDetailView skill={skill} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function SkillFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: Skill) => {
    void queryClient.invalidateQueries({ queryKey: skillKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <SkillForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: SkillDetailScreen,
  FormScreen: SkillFormScreen,
}
