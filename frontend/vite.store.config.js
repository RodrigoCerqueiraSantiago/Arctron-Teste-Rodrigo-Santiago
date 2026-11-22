import { resolve } from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: resolve(import.meta.dirname, '../docker/woocommerce/arctron-store/assets'),
    emptyOutDir: false,
    rollupOptions: {
      input: resolve(import.meta.dirname, 'src/store.css'),
      output: { assetFileNames: 'store.css' },
    },
  },
})
