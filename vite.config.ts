import path from 'node:path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  publicDir: false,
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/react'),
    },
  },
  build: {
    manifest: 'manifest.json',
    outDir: 'public/build',
    rollupOptions: {
      input: 'resources/react/main.tsx',
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5174,
  },
})
