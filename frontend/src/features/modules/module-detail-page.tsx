import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

interface ModuleDetailPageProps {
  /** The registry domain this route was generated for (spec 0042). */
  domain: string
}

/**
 * Generic dedicated read-only page for any registered module (spec 0042):
 * back-to-list + module-gated "Edit" (`<Can>`, an affordance only — the
 * backend re-authorizes) + the domain's `DetailScreen`. Replaces the
 * per-module `*-detail-page.tsx` files for the 4 Wave 0 modules, whose
 * content/chrome this mirrors exactly; `DetailPageActions`, when the
 * registry entry declares one, renders between "Back" and "Edit" (e.g.
 * leads' "Create/Go to opportunity"). When the registry entry sets
 * `detailOwnsEditAction`, the header Edit button is omitted and `onEdit` is
 * handed to `DetailScreen` instead, so the module's own read-only view
 * renders the single Edit affordance itself.
 */
export default function ModuleDetailPage({ domain }: ModuleDetailPageProps) {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const entityId = parseEntityId(id)

  const entry = getModuleRegistryEntry(domain)
  if (!entry) {
    throw new Error(`ModuleDetailPage: "${domain}" is not registered in the module registry.`)
  }

  if (entityId === null) {
    return <NotFoundPage />
  }

  const { DetailScreen, DetailPageActions } = entry

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to={entry.basePath}>
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {DetailPageActions ? <DetailPageActions id={entityId} /> : null}
            {!entry.detailOwnsEditAction && (
              <Can permission={`${domain}.update`}>
                <Button asChild>
                  <Link to={`${entry.basePath}/${entityId}/edit`}>
                    <Pencil aria-hidden="true" />
                    {t('common.edit')}
                  </Link>
                </Button>
              </Can>
            )}
          </>
        }
      />

      <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
        <DetailScreen
          id={entityId}
          onEdit={() => void navigate(`${entry.basePath}/${entityId}/edit`)}
        />
      </div>
    </div>
  )
}
