import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { API_BASE } from '@/services/api'

;(window as any).Pusher = Pusher

let echoInstance: Echo<any> | null = null

export const getEcho = (): Echo<any> | null => {
  if (echoInstance) return echoInstance

  const key = import.meta.env.VITE_REVERB_APP_KEY || 'rkuosxtpxzmw31k1vusy'
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname || 'localhost'
  const port = import.meta.env.VITE_REVERB_PORT || 8080
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
  const apiBase = API_BASE
  const authEndpoint = apiBase.replace(/\/api\/?$/, '') + '/api/broadcasting/auth'

  try {
    echoInstance = new Echo({
      broadcaster: 'reverb',
      key: key,
      wsHost: host,
      wsPort: Number(port),
      wssPort: Number(port),
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: authEndpoint,
      authorizer: (channel: any) => {
        return {
          authorize: (socketId: string, callback: Function) => {
            const token = localStorage.getItem('lapaqu_token')
            fetch(authEndpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': token ? `Bearer ${token}` : '',
              },
              body: JSON.stringify({
                socket_id: socketId,
                channel_name: channel.name,
              }),
            })
              .then(res => {
                if (!res.ok) throw new Error(`Auth failed: ${res.status}`)
                return res.json()
              })
              .then(data => callback(null, data))
              .catch(err => {
                console.warn('[Echo Reverb] Channel authorization error:', err)
                callback(err, null)
              })
          },
        }
      },
    })
  } catch (err) {
    console.warn('Echo initialization failed:', err)
  }

  return echoInstance
}

export const disconnectEcho = () => {
  if (echoInstance) {
    try {
      echoInstance.disconnect()
    } catch (e) {}
    echoInstance = null
  }
}
