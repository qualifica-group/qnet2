import type { ReactNode } from 'react'

interface RecordSkeletonSectionsProps {
  details?: ReactNode
  context?: ReactNode
  contacts?: ReactNode
  rewards?: ReactNode
  team?: ReactNode
  company?: ReactNode
  document?: ReactNode
  /** The record's own last block (Contratto: lifecycle; Commessa: contract data). */
  specific?: ReactNode
}

/**
 * The ONE place that fixes the order of the sections every record detail
 * shares (spec 0331 D-3), so Opportunita', Offerta, Contratto and Commessa
 * cannot drift apart. Rendered as a fragment inside `RecordSectionsGrid`
 * (2 columns, row by row): Dati | Contesto, Anagrafica e contatti | Buoni,
 * Team | Societa' e sedi, Documento e pagamento | specific. A missing slot
 * renders nothing: the record simply lacks that section.
 */
export function RecordSkeletonSections({
  details,
  context,
  contacts,
  rewards,
  team,
  company,
  document,
  specific,
}: RecordSkeletonSectionsProps) {
  return (
    <>
      {details}
      {context}
      {contacts}
      {rewards}
      {team}
      {company}
      {document}
      {specific}
    </>
  )
}
