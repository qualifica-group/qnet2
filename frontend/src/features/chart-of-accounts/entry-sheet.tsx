import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'

interface EntrySheetProps {
  title: string
  description: string
  formId: string
  isPending: boolean
  onClose: () => void
  children: ReactNode
}

/** Shell shared by the chart-of-accounts forms: header, scrollable body, Save/Cancel footer. */
export function EntrySheet({ title, description, formId, isPending, onClose, children }: EntrySheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent resizable={false} className="w-full gap-0 sm:max-w-md" storageKey="chart-of-accounts">
        <SheetHeader className="border-b bg-surface p-3">
          <SheetTitle className="text-base">{title}</SheetTitle>
          <SheetDescription className="text-xs">{description}</SheetDescription>
        </SheetHeader>
        <div className="flex-1 overflow-y-auto p-3">{children}</div>
        <SheetFooter className="flex-row justify-end border-t bg-surface p-3">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('chartOfAccounts.form.cancel')}
          </Button>
          <Button type="submit" form={formId} size="sm" disabled={isPending}>
            {isPending ? t('chartOfAccounts.form.saving') : t('chartOfAccounts.form.save')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
