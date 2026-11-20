import { RecordTabCount } from '@/components/detail/record-tab-icon'
import { useAttachmentList } from '@/features/attachments/use-attachments'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'

interface DocumentsTabCountProps {
  resource: string
  id: number
  collection?: string
}

/** Count of a Documenti tab: the files the user has not read yet, from the same list `DocumentsSection` shows. */
export function DocumentsTabCount({ resource, id, collection = DOCUMENTS_COLLECTION }: DocumentsTabCountProps) {
  const { data } = useAttachmentList(resource, id, collection)

  return <RecordTabCount count={data?.unread} />
}
