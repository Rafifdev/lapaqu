<script setup lang="ts">
import { ref, inject, computed, nextTick, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, ShieldAlert } from 'lucide-vue-next'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import apiClient from '@/services/api'

const router = useRouter()
const route = useRoute()

const existingPairedOutlet = ref<{ id: string; name: string } | null>(null)

// State loading skeleton dari parent layout
const isPageLoading = inject('isAuthPageLoading', ref(false))

const otpLength = 6
const otpDigits = ref<string[]>(Array(otpLength).fill(''))
const inputRefs = ref<HTMLInputElement[]>([])

const loading = ref(false)
const errorMessage = ref('')
const isPairingFailed = ref(false)

const codeTouched = ref(false)
const codeError = ref('')

const pairingCode = computed(() => otpDigits.value.join(''))

onMounted(() => {
  const saved = localStorage.getItem('lapaqu_paired_outlet')
  if (saved) {
    try {
      const parsed = JSON.parse(saved)
      if (parsed?.id) {
        existingPairedOutlet.value = parsed
        // Jika sudah dipair dan bukan relink eksplisit, langsung redirect ke outlet-staff
        if (route.query.relink !== '1') {
          router.replace('/auth/outlet-staff')
          return
        }
      }
    } catch { }
  }

  nextTick(() => {
    inputRefs.value[0]?.focus()
  })
})

const validateCode = () => {
  const code = pairingCode.value.trim()
  if (!code) {
    codeError.value = 'Silakan masukkan kode pairing outlet'
    return false
  }
  if (code.length < otpLength) {
    codeError.value = `Masukkan ${otpLength} digit kode pairing secara lengkap`
    return false
  }
  codeError.value = ''
  return true
}

const handleInput = (idx: number, e: Event) => {
  const target = e.target as HTMLInputElement
  const rawVal = target.value
  errorMessage.value = ''
  isPairingFailed.value = false

  if (rawVal) {
    const char = rawVal.slice(-1).toUpperCase()
    otpDigits.value[idx] = char
    target.value = char

    if (idx < otpLength - 1) {
      nextTick(() => {
        inputRefs.value[idx + 1]?.focus()
        inputRefs.value[idx + 1]?.select()
      })
    }
  } else {
    otpDigits.value[idx] = ''
  }

  if (codeTouched.value) {
    validateCode()
  }

  if (otpDigits.value.every((d) => d !== '')) {
    handlePairing()
  }
}

const handleKeyDown = (idx: number, e: KeyboardEvent) => {
  if (e.key === 'Backspace') {
    if (!otpDigits.value[idx] && idx > 0) {
      otpDigits.value[idx - 1] = ''
      nextTick(() => {
        inputRefs.value[idx - 1]?.focus()
      })
    } else {
      otpDigits.value[idx] = ''
    }
  } else if (e.key === 'ArrowLeft' && idx > 0) {
    e.preventDefault()
    inputRefs.value[idx - 1]?.focus()
  } else if (e.key === 'ArrowRight' && idx < otpLength - 1) {
    e.preventDefault()
    inputRefs.value[idx + 1]?.focus()
  }
}

const handlePaste = (e: ClipboardEvent) => {
  e.preventDefault()
  errorMessage.value = ''
  isPairingFailed.value = false
  const pasted = e.clipboardData?.getData('text').trim().toUpperCase() || ''
  if (!pasted) return

  const chars = pasted.replace(/[^A-Z0-9]/gi, '').slice(0, otpLength).split('')
  chars.forEach((ch, i) => {
    otpDigits.value[i] = ch
  })

  const nextFocusIndex = Math.min(chars.length, otpLength - 1)
  nextTick(() => {
    inputRefs.value[nextFocusIndex]?.focus()
  })

  if (codeTouched.value) {
    validateCode()
  }

  if (otpDigits.value.every((d) => d !== '')) {
    handlePairing()
  }
}

const handlePairing = async () => {
  codeTouched.value = true
  if (!validateCode()) return

  loading.value = true
  errorMessage.value = ''
  isPairingFailed.value = false

  try {
    const response = await apiClient.post('/auth/outlet-pairing', {
      pairing_code: pairingCode.value.trim(),
    })

    if (response.data?.token) {
      if (response.data?.outlet) {
        localStorage.setItem('lapaqu_paired_outlet', JSON.stringify(response.data.outlet))
      }
      localStorage.setItem('lapaqu_device_token', response.data.token)
      router.push('/auth/outlet-staff')
    }
  } catch (err: any) {
    isPairingFailed.value = true
    errorMessage.value = err?.response?.data?.message || 'Kode pairing tidak valid atau telah kedaluwarsa'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <!-- Form Container: Lebar maks 512px persis seperti Register & Login -->
  <div class="w-full max-w-[512px] mx-auto flex flex-col justify-center animate-outlet-fade">

    <!-- Header: Judul & Keterangan (Left-aligned persis seperti OTP Register) -->
    <div class="text-left shrink-0 mb-0">
      <template v-if="isPageLoading">
        <AppSkeleton width="220px" height="28px" rounded="md" />
        <AppSkeleton width="340px" height="14px" rounded="md" class="mt-2" />
      </template>
      <template v-else>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-tight">
          Verifikasi Koneksi Outlet
        </h1>
        <p class="text-sm sm:text-base text-[#64748B] dark:text-[#94A3B8] font-normal mt-1.5 leading-relaxed">
          Masukkan 6 karakter kode pairing untuk menghubungkan perangkat ini ke outlet.
        </p>
      </template>
    </div>

    <!-- Alert jika perangkat belum melakukan pairing (redirect dari outlet-staff) -->
    <div v-if="route.query.alert === 'unpaired' && !existingPairedOutlet && !isPageLoading"
      class="w-full mt-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-center gap-2 text-xs sm:text-sm text-amber-800 dark:text-amber-200">
      <ShieldAlert class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
      <span>Perangkat ini belum terhubung ke outlet. Silakan masukkan kode pairing terlebih dahulu.</span>
    </div>

    <!-- Banner info jika perangkat sudah terhubung sebelumnya (mode relink) -->
    <div v-if="existingPairedOutlet && !isPageLoading"
      class="w-full mt-3 p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 flex items-center justify-between text-xs sm:text-sm">
      <div class="flex items-center gap-2 text-[#4880FF] font-medium truncate">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
        <span class="truncate">Saat ini terhubung: <strong>{{ existingPairedOutlet.name }}</strong></span>
      </div>
      <button type="button" @click="router.push('/auth/outlet-staff')"
        class="text-xs sm:text-sm font-bold text-[#4880FF] hover:underline shrink-0 ml-2 cursor-pointer">
        Pilih Staff &rarr;
      </button>
    </div>

    <!-- Reserved Error Slot (Tinggi tetap konsisten h-6 sm:h-7 persis seperti OTP Register) -->
    <div class="flex items-center h-6 sm:h-7">
      <p v-if="!isPageLoading"
        :class="(errorMessage || codeError) ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
        class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
        {{ (errorMessage || codeError) || '&nbsp;' }}
      </p>
    </div>

    <!-- ================= FORM PAIRING OUTLET ================= -->
    <form @submit.prevent="handlePairing" autocomplete="off" class="flex flex-col gap-6">
      <!-- 6-digit OTP Inputs (Responsif persis seperti OTP Register, desktop sm:w-18 sm:h-22) -->
      <div class="flex justify-center gap-1.5 min-[360px]:gap-2 min-[390px]:gap-2.5 min-[480px]:gap-3 sm:gap-3.5 w-full py-1.5" @paste="handlePaste">
        <template v-if="isPageLoading">
          <AppSkeleton v-for="i in 6" :key="i"
            class="w-10 h-14 min-[360px]:w-11 min-[360px]:h-16 min-[390px]:w-12 min-[390px]:h-[68px] min-[480px]:w-14 min-[480px]:h-20 sm:w-18 sm:h-22 rounded-lg min-[360px]:rounded-xl sm:rounded-2xl" />
        </template>
        <template v-else>
          <input v-for="(digit, idx) in otpDigits" :key="idx"
            :ref="(el) => { if (el) inputRefs[idx] = el as HTMLInputElement }" :id="`otp-${idx}`" type="text"
            maxlength="1" :value="digit" @input="handleInput(idx, $event)" @keydown="handleKeyDown(idx, $event)"
            :style="{ animationDelay: `${idx * 40}ms` }"
            class="animate-otp-pop w-10 h-14 min-[360px]:w-11 min-[360px]:h-16 min-[390px]:w-12 min-[390px]:h-[68px] min-[480px]:w-14 min-[480px]:h-20 sm:w-18 sm:h-22 text-center text-lg min-[360px]:text-xl min-[390px]:text-2xl sm:text-3xl font-black font-mono uppercase rounded-lg min-[360px]:rounded-xl sm:rounded-2xl bg-white dark:bg-[#273142] text-[#1E293B] dark:text-white transition-all duration-150 focus:outline-none shadow-sm"
            :class="[
              (codeError || isPairingFailed)
                ? 'border-2 border-[#EC4453] dark:border-[#EC4453] focus:ring-2 focus:ring-[#EC4453]/20'
                : 'border border-[#CBD5E1] dark:border-[#334155] hover:border-[#94A3B8] dark:hover:border-[#475569] focus:border-[#4880FF] focus:ring-2 focus:ring-[#4880FF]/25'
            ]" />
        </template>
      </div>

      <!-- Action Links: Kembali ke Login (kiri) & Status Link (kanan) -->
      <div class="flex items-center justify-between text-sm sm:text-base">
        <template v-if="isPageLoading">
          <AppSkeleton width="120px" height="18px" rounded="md" />
        </template>
        <template v-else>
          <button type="button" @click="router.push('/auth/login')"
            class="inline-flex items-center gap-1.5 font-semibold text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] dark:hover:text-[#4880FF] transition-colors cursor-pointer">
            <ArrowLeft class="w-4 h-4" />
            <span>Kembali ke Login</span>
          </button>

          <button v-if="existingPairedOutlet" type="button" @click="router.push('/auth/outlet-staff')"
            class="font-bold text-[#4880FF] hover:underline cursor-pointer">
            Pilih Staff &rarr;
          </button>
        </template>
      </div>

      <!-- Submit Pairing Button (Persis tombol Verifikasi OTP di Register) -->
      <div>
        <template v-if="isPageLoading">
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <button type="submit" :disabled="loading || pairingCode.length !== 6"
            class="w-full h-11 rounded-xl bg-[#4880FF] hover:bg-[#3B6FE8] active:scale-[0.99] text-white text-sm sm:text-base font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
            <svg v-if="loading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
              </path>
            </svg>
            <span>{{ loading ? 'Menghubungkan...' : 'Hubungkan Perangkat' }}</span>
          </button>
        </template>
      </div>
    </form>
  </div>
</template>

<style scoped>
/* Smooth Entrance Animations for Outlet Login */
@keyframes outletFadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes otpPopIn {
  0% {
    opacity: 0;
    transform: translateY(8px) scale(0.95);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.animate-outlet-fade {
  animation: outletFadeIn 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-otp-pop {
  animation: otpPopIn 0.26s cubic-bezier(0.16, 1, 0.3, 1) backwards;
}

@media (prefers-reduced-motion: reduce) {
  .animate-outlet-fade,
  .animate-otp-pop {
    animation: none !important;
  }
}

/* Chrome, Safari, Edge, Opera: hide spin buttons for numeric if any */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
