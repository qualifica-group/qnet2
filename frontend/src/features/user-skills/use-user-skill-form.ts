import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createUserSkill, updateUserSkill } from '@/features/user-skills/api'
import {
  EMPTY_USER_SKILL_FORM,
  toUserSkillPayload,
  userSkillFormValues,
} from '@/features/user-skills/user-skill-payload'
import { buildUserSkillSchema, type UserSkillFormValues } from '@/features/user-skills/user-skill-schema'
import { useUserSkillRefresh } from '@/features/user-skills/use-user-skill-refresh'
import type { UserSkill } from '@/features/user-skills/types'

/** Server-side field names mapped onto the form for 422 handling (the person-skill pair already present answers on `skill_id`). */
const SERVER_ERROR_FIELDS = ['user_id', 'skill_id', 'level', 'assessed_on', 'notes'] as const

interface UseUserSkillFormArgs {
  /** The person skill being edited; omitted for a new one. */
  userSkill?: UserSkill
  refreshGrid?: () => void
  onSaved: (saved: UserSkill) => void
}

/** Owns every non-render concern of the person skill form: RHF/Zod wiring, create/update call and 422 mapping. */
export function useUserSkillForm({ userSkill, refreshGrid, onSaved }: UseUserSkillFormArgs) {
  const { t } = useTranslation()
  const refresh = useUserSkillRefresh(refreshGrid)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const schema = useMemo(() => buildUserSkillSchema(t), [t])
  const form = useForm<UserSkillFormValues>({
    resolver: zodResolver(schema),
    defaultValues: userSkill ? userSkillFormValues(userSkill) : EMPTY_USER_SKILL_FORM,
  })

  async function persist(values: UserSkillFormValues) {
    const payload = toUserSkillPayload(values)
    if (payload === null) {
      return
    }
    setIsSubmitting(true)
    try {
      const saved = userSkill ? await updateUserSkill(userSkill.id, payload) : await createUserSkill(payload)
      refresh(saved.id)
      toast.success(t(userSkill ? 'userSkills.form.updated' : 'userSkills.form.created'))
      onSaved(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(serverMessage(error) ?? t('userSkills.form.error'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const submit = form.handleSubmit(persist)

  return { form, isSubmitting, submit }
}
