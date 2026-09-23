import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd, ConfigProvider } from 'antd'
import enUS from 'antd/locale/en_US'
import frFR from 'antd/locale/fr_FR'
import { HelmetProvider } from 'react-helmet-async'
import { useTranslation } from 'react-i18next'
import { BrowserRouter } from 'react-router-dom'
import { relierSessionEtClient } from '../features/auth/api'
import { RoutesApplication } from '../routes/RoutesApplication'
import '../shared/i18n/langues' // applique tout de suite la langue mémorisée (dayjs, <html lang>)
import { themeAntd } from '../shared/theme/jetons'

relierSessionEtClient()

const clientRequetes = new QueryClient({
  defaultOptions: { queries: { staleTime: 30_000, refetchOnWindowFocus: false } },
})

const LOCALES_ANTD = { fr: frFR, en: enUS }

export function App() {
  const { i18n } = useTranslation()
  const localeAntd = LOCALES_ANTD[i18n.language as keyof typeof LOCALES_ANTD] ?? frFR

  return (
    <ConfigProvider theme={themeAntd} locale={localeAntd}>
      {/* AppAntd fournit messages et modales : aucune fenêtre native du navigateur (CdC § 6.8). */}
      <AppAntd>
        <HelmetProvider>
          <QueryClientProvider client={clientRequetes}>
            <BrowserRouter>
              <RoutesApplication />
            </BrowserRouter>
          </QueryClientProvider>
        </HelmetProvider>
      </AppAntd>
    </ConfigProvider>
  )
}
