import { useTranslation } from 'react-i18next'
import { SquareArrowOutUpRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'

interface SheetDetailPageLinkProps {
  /** Registry domain whose dedicated detail page (`${basePath}/:id`) is opened. */
  domain: string
  id: number
  /**
   * Closes the hosting Sheet and navigates to `path`. Owned by the host
   * because not every Sheet lives inside the router context (the user detail
   * provider is mounted above `RouterProvider`), and because the Sheet does
   * not always unmount on navigation, so it must be closed explicitly.
   */
  onOpen: (path: string) => void
}

/** `SheetToolbar` action that leaves the modal for the record's dedicated detail page. */
export function SheetDetailPageLink({ domain, id, onOpen }: SheetDetailPageLinkProps) {
  const { t } = useTranslation()
  const basePath = getModuleRegistryEntry(domain)?.basePath

  if (basePath === undefined) {
    return null
  }

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-xs"
      onClick={() => onOpen(`${basePath}/${id}`)}
      aria-label={t('common.openDetailPage')}
      title={t('common.openDetailPage')}
    >
      <SquareArrowOutUpRight className="size-3.5" aria-hidden="true" />
    </Button>
  )
}
