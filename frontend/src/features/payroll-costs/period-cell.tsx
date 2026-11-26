import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatPeriod } from './format-period'

/** The month of a row ("YYYY-MM" from the backend) in words, in the UI language. */
export function PeriodCell({ value }: ICellRendererParams) {
  return typeof value === 'string' ? <span>{formatPeriod(value, i18n.language)}</span> : null
}
