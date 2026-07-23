import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { moduleI18nNamespace } from '@/features/modules/i18n-namespace'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import type { ModuleFormScreenMode } from '@/features/modules/types'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

interface ModuleFormPageProps {
  /** The registry domain this route was generated for (spec 0042). */
  domain: string
  /**
   * When set to `'duplicate'`, this instance renders `${basePath}/:id/duplicate`
   * (row action "duplicate"): a create form pre-filled from the source `id`,
   * gated by `.create` (not `.update`) since it still submits via the create
   * path.
   */
  variant?: 'duplicate'
}

/**
 * Generic dedicated create/edit/duplicate page for any registered module
 * (spec 0042). Serves `${basePath}/new` (no `:id`), `${basePath}/:id/edit`
 * and `${basePath}/:id/duplicate`, gated by the matching `.create`/`.update`
 * permission. Replaces the per-module `*-form-page.tsx` files for the 4 Wave 0
 * modules: the header chrome (title/subtitle) is generic because every module
 * already follows the same `${namespace}.form.{create,edit}{Title,Subtitle}`
 * i18n convention, where the namespace is the camelCase form of the domain
 * (duplicate reuses the create strings); everything else
 * (fetch-for-edit/duplicate, the actual form) lives in the domain's
 * `FormScreen`.
 */
export default function ModuleFormPage({ domain, variant }: ModuleFormPageProps) {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const entry = getModuleRegistryEntry(domain)
  // Only used so the hooks below stay unconditional (rules-of-hooks) up to
  // the invariant check right before render.
  const basePath = entry?.basePath ?? ''
  // Strings are keyed by the camelCase namespace, NOT the kebab-case domain:
  // interpolating `domain` renders the raw key as the title for every
  // multi-word module. The permission gate below keeps `domain` — permissions
  // really are kebab-case (`reward-types.create`).
  const ns = moduleI18nNamespace(domain)

  const isDuplicate = variant === 'duplicate'
  const isEdit = id !== undefined && !isDuplicate
  const entityId = parseEntityId(id)

  const onSuccess = useCallback(
    (savedId: number) => {
      void navigate(`${basePath}/${savedId}`)
    },
    [navigate, basePath],
  )

  const onCancel = useCallback(() => {
    if (isDuplicate) {
      void navigate(`${basePath}/${entityId}`)
      return
    }
    void navigate(isEdit ? `${basePath}/${entityId}` : basePath)
  }, [isDuplicate, isEdit, navigate, basePath, entityId])

  if (!entry) {
    throw new Error(`ModuleFormPage: "${domain}" is not registered in the module registry.`)
  }

  if ((isEdit || isDuplicate) && entityId === null) {
    return <NotFoundPage />
  }

  const { FormScreen } = entry

  // Narrow on `entityId` itself so TS refines it to `number` without a cast:
  // the `(isEdit || isDuplicate) && entityId === null` case already returned
  // above, so here `entityId !== null` is exactly "edit or duplicate". The
  // create branch is the FormScreen's only params channel (spec 0045): a bare
  // `${basePath}/new` yields `params: undefined`, so consumers no longer need
  // their own `useSearchParams()` for a deep-linked create.
  let mode: ModuleFormScreenMode
  if (isDuplicate && entityId !== null) {
    mode = { type: 'duplicate', id: entityId }
  } else if (entityId !== null) {
    mode = { type: 'edit', id: entityId }
  } else {
    const query = searchParams.toString()
    mode = { type: 'create', params: query ? Object.fromEntries(searchParams) : undefined }
  }

  return (
    <Can
      permission={`${domain}.${isEdit ? 'update' : 'create'}`}
      fallback={<p className="text-sm text-muted-foreground">{t(`${ns}.forbidden`)}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <header className="flex flex-col gap-1 border-b px-4 py-3">
            <h2 className="text-base font-semibold">
              {t(`${ns}.form.${isEdit ? 'edit' : 'create'}Title`)}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t(`${ns}.form.${isEdit ? 'edit' : 'create'}Subtitle`)}
            </p>
          </header>

          <FormScreen mode={mode} onSuccess={onSuccess} onCancel={onCancel} />
        </div>
      </div>
    </Can>
  )
}
