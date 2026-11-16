import { RecordTabCount } from '@/components/detail/record-tab-icon'
import { useAttachmentList } from '@/features/attachments/use-attachments'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'

interface DocumentsTabCountProps {
  resource: string
  id: number
  collection?: string
}

/** Count of a Documenti tab: the same list `DocumentsSection` shows, so an upload or delete updates it. */
export function DocumentsTabCount({ resource, id, collection = DOCUMENTS_COLLECTION }: DocumentsTabCountProps) {
  const { data } = useAttachmentList(resource, id, collection)

  return <RecordTabCount count={data?.documents.length} />
}
