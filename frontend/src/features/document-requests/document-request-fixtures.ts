import { AxiosError } from 'axios'
import type { DocumentRequest, DocumentRequestItem } from '@/features/document-requests/types'

export function buildItem(overrides: Partial<DocumentRequestItem> = {}): DocumentRequestItem {
  return {
    id: 1,
    section_index: 0,
    name: 'Visura camerale',
    description: null,
    is_required: true,
    min_files: 1,
    max_files: 1,
    status: 'ok',
    status_reason: null,
    latest_validation: null,
    documents_count: 1,
    ...overrides,
  }
}

export function buildRequest(overrides: Partial<DocumentRequest> = {}): DocumentRequest {
  return {
    id: 10,
    work_order_id: 5,
    checklist: { id: 3, code: 'ONBOARD', name: 'Documenti societari' },
    category_name: 'Societa',
    due_date: '2027-03-15',
    recipient_email: 'cliente@example.com',
    message: null,
    status: 'in_progress',
    title: 'Documenti societari - ACME',
    items_count: 2,
    items_uploaded_count: 2,
    items_satisfied_count: 1,
    sections: [{ name: 'Societa', description: null }],
    items: [
      buildItem(),
      buildItem({ id: 2, name: 'Statuto', status: 'ko', status_reason: 'Documento illeggibile', documents_count: 2 }),
      buildItem({ id: 3, section_index: null, name: 'Delega', status: 'pending', documents_count: 0, is_required: false }),
    ],
    sent_at: '2027-03-01T10:00:00Z',
    last_reminded_at: null,
    completed_at: null,
    cancelled_at: null,
    expired_at: null,
    link_expires_at: null,
    remote_updated_at: '2027-03-02T10:00:00Z',
    delivery_status: 'delivered',
    pending_operation: null,
    delivery_error: null,
    created_at: '2027-03-01T09:00:00Z',
    permissions: { update: true, remind: true, cancel: true },
    ...overrides,
  }
}

export function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}
