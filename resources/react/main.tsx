import React from 'react'
import { createRoot } from 'react-dom/client'

import App from './App'
import { BrandingProvider } from './lib/branding'
import { SpaRouter } from './lib/spa-router'
import './styles.css'

const root = document.getElementById('react-modernization-root')

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <BrandingProvider>
        <SpaRouter>
          <App />
        </SpaRouter>
      </BrandingProvider>
    </React.StrictMode>,
  )
}
