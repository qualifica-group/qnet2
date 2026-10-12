/** Human label of a passive document: its number, or `#id` while it has none yet (e.g. a proforma). */
export function purchaseDocumentLabel(document: { id: number; document_number: string | null }): string {
  return document.document_number && document.document_number !== '' ? document.document_number : `#${document.id}`
}
