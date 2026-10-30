import { createContext } from 'react'

/** The sheet a preview block is drawn on, read by the page-number field runs deep inside any block. */
export interface PreviewPageNumbers {
  page: number
  totalPages: number
}

export const PreviewPageContext = createContext<PreviewPageNumbers>({ page: 1, totalPages: 1 })
