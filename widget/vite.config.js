import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'

export default defineConfig({
  plugins: [preact()],
  build: {
    outDir: '../public',
    emptyOutDir: false,
    lib: {
      entry: 'src/main.jsx',
      name: 'AIChatWidget',
      formats: ['iife'],
      fileName: () => 'widget.js'
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') return 'css/widget.css';
          return assetInfo.name;
        }
      }
    }
  }
})
