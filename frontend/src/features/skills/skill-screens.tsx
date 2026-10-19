/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { skillKeys, fetchSkill } from '@/features/skills/api'
import { SkillForm } from '@/features/skills/skill-form'
import { SkillDetailView } from '@/features/skills/skill-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { SKILLS_DOMAIN, type Skill } from '@/features/skills/types'

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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: SKILLS_DOMAIN,
  basePath: `/${SKILLS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.skills',
  DetailScreen: SkillDetailScreen,
  FormScreen: SkillFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
