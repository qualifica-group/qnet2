import { useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useCreateWorkspace, useUpdateWorkspace } from '@/features/workspaces/use-workspaces'
import {
  buildWorkspacePayload,
  buildWorkspaceSchema,
  EMPTY_WORKSPACE_FORM,
  workspaceToFormValues,
  type WorkspaceFormValues,
} from '@/features/workspaces/workspace-schema'
import { slugify } from '@/features/workspaces/workspace-slug'
import type { Workspace } from '@/features/workspaces/types'

const SERVER_ERROR_FIELDS: Path<WorkspaceFormValues>[] = [
  'name',
  'description',
  'slug',
  'icon',
  'color',
  'sort_order',
  'navigation_keys',
]

/**
 * RHF/Zod wiring and the create/update submit of the app form. On create the
 * address follows the name until it is typed by hand; clearing it resumes.
 */
export function useWorkspaceForm({ workspace, onSaved }: { workspace: Workspace | null; onSaved: () => void }) {
  const { t } = useTranslation('workspaces')
  const [serverError, setServerError] = useState<string | null>(null)
  const createMutation = useCreateWorkspace()
  const updateMutation = useUpdateWorkspace(workspace?.id ?? 0)

  const schema = useMemo(() => buildWorkspaceSchema(t), [t])
  const form = useForm<WorkspaceFormValues>({
    resolver: zodResolver(schema),
    defaultValues: workspace ? workspaceToFormValues(workspace) : EMPTY_WORKSPACE_FORM,
  })
  const slugEditedByHand = useRef(workspace !== null)

  const followName = (name: string) => {
    if (!slugEditedByHand.current) {
      form.setValue('slug', slugify(name), { shouldDirty: true, shouldValidate: form.formState.isSubmitted })
    }
  }

  const markSlugEdited = (slug: string) => {
    slugEditedByHand.current = slug !== ''
  }

  const onSubmit = async (values: WorkspaceFormValues) => {
    setServerError(null)
    const payload = buildWorkspacePayload(values)
    try {
      if (workspace) {
        await updateMutation.mutateAsync(payload)
        toast.success(t('form.updated'))
      } else {
        await createMutation.mutateAsync(payload)
        toast.success(t('form.created'))
      }
      onSaved()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('form.saveError'))
      }
    }
  }

  return { form, serverError, onSubmit, followName, markSlugEdited }
}
