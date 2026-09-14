import mandiriSvg from '@/assets/payment_method/Bank Mandiri.svg'
import briSvg from '@/assets/payment_method/Bank Rakyat Indonesia (BRI).svg'
import bniSvg from '@/assets/payment_method/Bank Negara Indonesia (BNI).svg'
import bsiSvg from '@/assets/payment_method/Bank BSI.svg'
import cimbSvg from '@/assets/payment_method/Bank CIMB Niaga.svg'
import permataSvg from '@/assets/payment_method/Bank Permata.svg'
import bjbSvg from '@/assets/payment_method/Bank bjb.svg'

export interface BankVAInfo {
  id: string
  code: string
  name: string
  shortName: string
  logo?: string
  bgColor: string
  textColor: string
  guide: {
    mbanking: string[]
    atm: string[]
  }
}

export const MAIN_VA_BANKS: BankVAInfo[] = [
  {
    id: 'va_bri',
    code: 'BRI',
    name: 'BRI Virtual Account',
    shortName: 'BRI',
    logo: briSvg,
    bgColor: '#00529C',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi BRImo dan login ke akun Anda.',
        'Pilih menu "Tagihan" lalu pilih "BRIVA".',
        'Masukkan nomor Virtual Account BRI Anda.',
        'Periksa nama dan nominal tagihan, lalu masukkan PIN BRImo untuk membayar.'
      ],
      atm: [
        'Masukkan kartu ATM BRI dan PIN Anda.',
        'Pilih menu "Transaksi Lain" > "Pembayaran" > "Lainnya" > "BRIVA".',
        'Masukkan nomor Virtual Account BRI.',
        'Konfirmasi rincian pembayaran, tekan "Ya" untuk memproses.'
      ]
    }
  },
  {
    id: 'va_mandiri',
    code: 'MANDIRI',
    name: 'Mandiri Virtual Account',
    shortName: 'Mandiri',
    logo: mandiriSvg,
    bgColor: '#003D79',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi Livin\' by Mandiri dan lakukan login.',
        'Pilih menu "Bayar" lalu pilih "Virtual Account".',
        'Pilih penyedia jasa atau masukkan nomor Virtual Account.',
        'Periksa nominal dan detail tagihan, lalu konfirmasi pembayaran dengan PIN Livin.'
      ],
      atm: [
        'Masukkan kartu ATM Mandiri dan PIN Anda.',
        'Pilih menu "Bayar/Beli" > "Lainnya" > "Multi Payment".',
        'Masukkan nomor Virtual Account.',
        'Periksa detail tagihan pada layar ATM, tekan "1" lalu "Ya" untuk menyelesaikan.'
      ]
    }
  },
  {
    id: 'va_bni',
    code: 'BNI',
    name: 'BNI Virtual Account',
    shortName: 'BNI',
    logo: bniSvg,
    bgColor: '#005E6A',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi BNI Mobile Banking dan lakukan login.',
        'Pilih menu "Transfer" lalu pilih "Virtual Account Billing".',
        'Pilih "Input Baru" dan masukkan nomor BNI Virtual Account Anda.',
        'Periksa informasi tagihan, masukkan Password Transaksi untuk menyelesaikan.'
      ],
      atm: [
        'Masukkan kartu ATM BNI dan PIN Anda.',
        'Pilih menu "Menu Lain" > "Transfer" > "Virtual Account Billing".',
        'Masukkan nomor Virtual Account BNI.',
        'Periksa rincian pembayaran di layar, tekan "Ya" untuk konfirmasi.'
      ]
    }
  }
]

export const OTHER_VA_BANKS: BankVAInfo[] = [
  {
    id: 'va_bsi',
    code: 'BSI',
    name: 'BSI Virtual Account',
    shortName: 'BSI',
    logo: bsiSvg,
    bgColor: '#00A39D',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi BSI Mobile dan lakukan login.',
        'Pilih menu "Bayar" lalu pilih "Virtual Account".',
        'Masukkan nomor Virtual Account BSI Anda.',
        'Konfirmasi rincian pembayaran dan masukkan PIN BSI Mobile Anda.'
      ],
      atm: [
        'Masukkan kartu ATM BSI dan PIN Anda.',
        'Pilih menu "Pembayaran/Beli" > "Virtual Account".',
        'Masukkan nomor BSI Virtual Account.',
        'Tekan "Benar" setelah memeriksa tagihan.'
      ]
    }
  },
  {
    id: 'va_cimb',
    code: 'CIMB',
    name: 'CIMB Niaga Virtual Account',
    shortName: 'CIMB Niaga',
    logo: cimbSvg,
    bgColor: '#ED1B24',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka OCTO Mobile dan login.',
        'Pilih menu "Transfer" > "Virtual Account".',
        'Masukkan nomor Virtual Account CIMB Niaga.',
        'Konfirmasi pembayaran dengan PIN OCTO Mobile.'
      ],
      atm: [
        'Masukkan kartu ATM CIMB Niaga dan PIN Anda.',
        'Pilih menu "Pembayaran" > "Lanjut" > "Virtual Account".',
        'Masukkan nomor Virtual Account CIMB Niaga.',
        'Tekan "Proses" untuk membayar.'
      ]
    }
  },
  {
    id: 'va_permata',
    code: 'PERMATA',
    name: 'Permata Virtual Account',
    shortName: 'Permata',
    logo: permataSvg,
    bgColor: '#009A44',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi PermataME dan login.',
        'Pilih menu "Bayar Tagihan" > "Virtual Account".',
        'Masukkan nomor Virtual Account Permata.',
        'Konfirmasi transaksi dan masukkan PIN.'
      ],
      atm: [
        'Masukkan kartu ATM Permata dan PIN Anda.',
        'Pilih menu "Transaksi Lainnya" > "Pembayaran" > "Virtual Account".',
        'Masukkan nomor Virtual Account Permata.',
        'Ikuti petunjuk di layar untuk menyelesaikan.'
      ]
    }
  },
  {
    id: 'va_bjb',
    code: 'BJB',
    name: 'BJB Virtual Account',
    shortName: 'BJB',
    logo: bjbSvg,
    bgColor: '#005CA9',
    textColor: '#FFFFFF',
    guide: {
      mbanking: [
        'Buka aplikasi DIGI by bank bjb dan lakukan login.',
        'Pilih menu "Transfer" lalu pilih "Virtual Account".',
        'Masukkan nomor Virtual Account BJB Anda.',
        'Periksa nominal tagihan, masukkan m-PIN untuk menyelesaikan.'
      ],
      atm: [
        'Masukkan kartu ATM bank bjb dan PIN Anda.',
        'Pilih menu "Transaksi Lainnya" > "Pembayaran" > "Virtual Account".',
        'Masukkan nomor Virtual Account BJB Anda.',
        'Periksa detail pembayaran, tekan "Ya" untuk memproses.'
      ]
    }
  }
]

export const ALL_VA_BANKS = [...MAIN_VA_BANKS, ...OTHER_VA_BANKS]

export function getBankVAInfo(methodOrCode: string): BankVAInfo | undefined {
  const normalized = methodOrCode.toLowerCase()
  return ALL_VA_BANKS.find(
    b => b.id.toLowerCase() === normalized || b.code.toLowerCase() === normalized || ('va_' + b.code.toLowerCase()) === normalized
  )
}
