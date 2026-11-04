import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, echoCtor, bind, unbind, connection } = vi.hoisted(() => {
  const bind = vi.fn()
  const unbind = vi.fn()
  const connection = { state: 'connecting', bind, unbind }
  const echoCtor = vi.fn()
  return { post: vi.fn(), echoCtor, bind, unbind, connection }
})

vi.mock('laravel-echo', () => ({
  default: class {
    connector = { pusher: { connection } }
    disconnect = vi.fn()
    constructor(options: unknown) {
      echoCtor(options)
    }
  },
}))
vi.mock('pusher-js', () => ({ default: class {} }))
vi.mock('@/api/client', () => ({ apiClient: { post } }))

import { BROADCAST_AUTH_PATH, getEcho, onEchoReconnect, resetEcho } from './echo'

type Authorizer = (channel: { name: string }) => {
  authorize: (socketId: string, cb: (e: Error | null, d: unknown) => void) => void
}

describe('echo client', () => {
  beforeEach(() => {
    resetEcho()
    vi.clearAllMocks()
    connection.state = 'connecting'
  })

  it('creates a single reverb instance lazily', () => {
    expect(echoCtor).not.toHaveBeenCalled()
    expect(getEcho()).toBe(getEcho())
    expect(echoCtor).toHaveBeenCalledTimes(1)
    expect(echoCtor.mock.calls[0][0]).toMatchObject({ broadcaster: 'reverb' })
  })

  it('authorizes channels through the project axios client', async () => {
    post.mockResolvedValue({ data: { auth: 'key:sig' } })
    getEcho()
    const { authorizer } = echoCtor.mock.calls[0][0] as { authorizer: Authorizer }
    const callback = vi.fn()

    authorizer({ name: 'private-work-orders.5.chat' }).authorize('123.456', callback)

    await vi.waitFor(() => expect(callback).toHaveBeenCalledWith(null, { auth: 'key:sig' }))
    expect(post).toHaveBeenCalledWith(BROADCAST_AUTH_PATH, {
      socket_id: '123.456',
      channel_name: 'private-work-orders.5.chat',
    })
  })

  it('reports authorization failures to pusher', async () => {
    post.mockRejectedValue(new Error('403'))
    getEcho()
    const { authorizer } = echoCtor.mock.calls[0][0] as { authorizer: Authorizer }
    const callback = vi.fn()

    authorizer({ name: 'private-x' }).authorize('1.2', callback)

    await vi.waitFor(() => expect(callback).toHaveBeenCalledWith(expect.any(Error), null))
  })

  it('fires the callback on reconnection only, not on the first connection', () => {
    const callback = vi.fn()
    const off = onEchoReconnect(callback)
    const handler = bind.mock.calls[0][1] as () => void

    handler()
    expect(callback).not.toHaveBeenCalled()
    handler()
    expect(callback).toHaveBeenCalledTimes(1)

    off()
    expect(unbind).toHaveBeenCalledWith('connected', handler)
  })
})
