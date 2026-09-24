import { Notyf } from 'notyf'
import 'notyf/notyf.min.css'

let notyfInstance: Notyf | null = null

// Tailwind CSS Default Colors:
// emerald-500: #10b981
// red-500:     #ef4444
// amber-500:   #f59e0b
// blue-500:    #3b82f6

// SVG Icons styled identical to Notyf native white circular badges (21px) with Tailwind colors
const warningIconSvg = `<svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto"><circle cx="10.5" cy="10.5" r="10.5" fill="#FFFFFF"/><path d="M10.5 5.75V11.5M10.5 14.75H10.51" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>`

const infoIconSvg = `<svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto"><circle cx="10.5" cy="10.5" r="10.5" fill="#FFFFFF"/><path d="M10.5 9.5V15M10.5 6.5H10.51" stroke="#3B82F6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>`

export function useNotyf() {
  if (typeof window !== 'undefined' && !notyfInstance) {
    notyfInstance = new Notyf({
      duration: 1500,
      position: {
        x: 'right',
        y: 'top',
      },
      dismissible: true,
      ripple: true,
      types: [
        {
          type: 'success',
          background: '#10B981', // Tailwind emerald-500
        },
        {
          type: 'error',
          background: '#EF4444', // Tailwind red-500
        },
        {
          type: 'warning',
          background: '#F59E0B', // Tailwind amber-500
          icon: warningIconSvg,
        },
        {
          type: 'info',
          background: '#3B82F6', // Tailwind blue-500
          icon: infoIconSvg,
        },
      ],
    })
  }

  const success = (message: string, duration?: number) => {
    if (duration !== undefined) {
      return notyfInstance?.open({
        type: 'success',
        message,
        duration,
      })
    }
    return notyfInstance?.success(message)
  }

  const error = (message: string, duration?: number) => {
    if (duration !== undefined) {
      return notyfInstance?.open({
        type: 'error',
        message,
        duration,
      })
    }
    return notyfInstance?.error(message)
  }

  const warning = (message: string, duration?: number) => {
    return notyfInstance?.open({
      type: 'warning',
      message,
      ...(duration !== undefined ? { duration } : {}),
    })
  }

  const info = (message: string, duration?: number) => {
    return notyfInstance?.open({
      type: 'info',
      message,
      ...(duration !== undefined ? { duration } : {}),
    })
  }

  return {
    notyf: notyfInstance,
    success,
    error,
    warning,
    info,
  }
}
