export type SupportedLocale = 'id' | 'en'

export interface LocaleOption {
  value: SupportedLocale
  label: string
}

export const SUPPORTED_LOCALES: LocaleOption[] = [
  { value: 'id', label: 'Bahasa Indonesia' },
  { value: 'en', label: 'English (US)' },
]
