import React from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

import App from './App'
import { BrandingProvider } from './lib/branding'
import { SpaRouter } from './lib/spa-router'
import './styles.css'

const root = document.getElementById('react-modernization-root')
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
})

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <BrandingProvider>
          <SpaRouter>
            <App />
          </SpaRouter>
        </BrandingProvider>
      </QueryClientProvider>
    </React.StrictMode>,
  )
}
