import { Suspense, useState, type ReactElement } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Can } from '@/features/auth/can'
import { resolveQuickCreate } from '@/features/quick-create/quick-create-registry'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

interface QuickCreateButtonProps {
  /** Resource segment of the for-select endpoint this button creates into, e.g. `sources`. */
  resource: string
  /** Called with the newly created record's `{id, name}` ref right before the dialog closes. */
  onCreated: (ref: RelationFieldRef) => void
  disabled?: boolean
}

/**
 * Icon-button "+" rendered next to a relation select, gated by the linked
 * module's registry entry and its `{domain}.create` permission (spec 0028
 * D4). Renders `null` when the resource has no entry (AC-011) or the actor
 * lacks the permission (AC-002) — the select then looks exactly as it does
 * today. Clicking it opens a centered Dialog with the module's real create
 * form, lazy-loaded so it never ships in the entry bundle (AC-013).
 */
export function QuickCreateButton({
  resource,
  onCreated,
  disabled = false,
}: QuickCreateButtonProps): ReactElement | null {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const entry = resolveQuickCreate(resource)
  if (!entry) return null

  const FormComponent = entry.form
  const title = t(entry.titleKey)

  const handleSuccess = (ref: RelationFieldRef) => {
    setOpen(false)
    onCreated(ref)
  }

  return (
    <Can permission={entry.permission}>
      <Dialog open={open} onOpenChange={setOpen}>
        <Button
          type="button"
          variant="outline"
          size="icon-xs"
          aria-label={title}
          disabled={disabled}
          onClick={() => setOpen(true)}
        >
          <Plus className="size-3.5" />
        </Button>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{t(entry.descriptionKey)}</DialogDescription>
          </DialogHeader>
          {/*
            React events bubble along the React tree, not the DOM tree: Radix
            portals this content out of the parent <form> in the DOM, but the
            synthetic `submit` of the inner form still reaches the parent form's
            onSubmit through the portal and submits it too (AC-008). The portal
            boundary is the only place that can stop it.
          */}
          <div className="max-h-[75vh] overflow-y-auto" onSubmit={(event) => event.stopPropagation()}>
            <Suspense fallback={<Skeleton className="h-48 w-full" />}>
              <FormComponent onSuccess={handleSuccess} onCancel={() => setOpen(false)} />
            </Suspense>
          </div>
        </DialogContent>
      </Dialog>
    </Can>
  )
}
