import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { apiClient } from '@/api/client'
import { env } from '@/config/env'

/** Relative to the api client baseURL (which already ends with `/api`). */
export const BROADCAST_AUTH_PATH = 'broadcasting/auth'

type ReverbEcho = Echo<'reverb'>

interface ChannelAuthorizationData {
  auth: string
  channel_data?: string
  shared_secret?: string
}

let instance: ReverbEcho | null = null

// Channel signing goes through the project axios client so the Bearer token and
// the 401 handling apply, instead of pusher-js' own XHR.
function authorizerFor(channel: { name: string }) {
  return {
    authorize: (
      socketId: string,
      callback: (error: Error | null, data: ChannelAuthorizationData | null) => void,
    ) => {
      apiClient
        .post<ChannelAuthorizationData>(BROADCAST_AUTH_PATH, {
          socket_id: socketId,
          channel_name: channel.name,
        })
        .then((response) => callback(null, response.data))
        .catch((error: unknown) =>
          callback(error instanceof Error ? error : new Error('Channel authorization failed'), null),
        )
    },
  }
}

/** Lazy singleton: the websocket opens on first use, never at import time. */
export function getEcho(): ReverbEcho {
  if (!instance) {
    instance = new Echo({
      broadcaster: 'reverb',
      Pusher,
      key: env.reverb.appKey,
      wsHost: env.reverb.host,
      wsPort: env.reverb.port,
      wssPort: env.reverb.port,
      forceTLS: env.reverb.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authorizer: authorizerFor,
    })
  }
  return instance
}

/**
 * Calls `callback` every time the socket connects AGAIN after a drop (the very
 * first connection is ignored: data was just fetched). Returns the unsubscribe.
 */
export function onEchoReconnect(callback: () => void): () => void {
  const { connection } = getEcho().connector.pusher
  let hasConnected = connection.state === 'connected'
  const handler = () => {
    if (hasConnected) {
      callback()
    }
    hasConnected = true
  }
  connection.bind('connected', handler)
  return () => connection.unbind('connected', handler)
}

/** Test helper: disconnects and drops the singleton. */
export function resetEcho(): void {
  instance?.disconnect()
  instance = null
}
