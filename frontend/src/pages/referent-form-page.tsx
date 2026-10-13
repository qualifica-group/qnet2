import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { referentDetailQueryKey } from '@/features/referents/api'
import { GuardedReferentForm } from '@/features/referents/guarded-referent-form'
import type { ReferentDetail } from '@/features/referents/types'

/**
 * Dedicated create page of a referent (`/referents/new`, spec 0022). There is
 * no edit page (spec 0216): the detail edits in place. The form sits behind
 * its leave guard, and a successful create lands on the new detail.
 */
export default function ReferentFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const onSuccess = (saved: ReferentDetail) => {
    queryClient.invalidateQueries({ queryKey: referentDetailQueryKey(saved.id) })
    void navigate(`/referents/${saved.id}`)
  }

  return (
    <Can
      permission="referents.create"
      fallback={<p className="text-sm text-muted-foreground">{t('referents.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        {/* The same chrome as the detail page: the leave guard intercepts the link. */}
        <PageHeader
          actions={
            <Button variant="outline" asChild>
              <Link to="/referents">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
          }
        />

        {/* No `bg-card`: the record canvas paints its own `bg-surface` and the
            cards inside it are the `bg-card` rung (ui-design.md §1-bis). */}
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          <GuardedReferentForm onSuccess={onSuccess} onCancel={() => void navigate('/referents')} />
        </div>
      </div>
    </Can>
  )
}
