// vite.config.js
import { defineConfig } from "file:///C:/project/glint-ai-chatbot/widget/node_modules/vite/dist/node/index.js";
import preact from "file:///C:/project/glint-ai-chatbot/widget/node_modules/@preact/preset-vite/dist/esm/index.mjs";
var vite_config_default = defineConfig({
  plugins: [preact()],
  build: {
    outDir: "../public",
    emptyOutDir: false,
    lib: {
      entry: "src/main.jsx",
      name: "AIChatWidget",
      formats: ["iife"],
      fileName: () => "widget.js"
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === "style.css") return "css/widget.css";
          return assetInfo.name;
        }
      }
    }
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxwcm9qZWN0XFxcXGdsaW50LWFpLWNoYXRib3RcXFxcd2lkZ2V0XCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCJDOlxcXFxwcm9qZWN0XFxcXGdsaW50LWFpLWNoYXRib3RcXFxcd2lkZ2V0XFxcXHZpdGUuY29uZmlnLmpzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy9DOi9wcm9qZWN0L2dsaW50LWFpLWNoYXRib3Qvd2lkZ2V0L3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSAndml0ZSdcbmltcG9ydCBwcmVhY3QgZnJvbSAnQHByZWFjdC9wcmVzZXQtdml0ZSdcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcbiAgcGx1Z2luczogW3ByZWFjdCgpXSxcbiAgYnVpbGQ6IHtcbiAgICBvdXREaXI6ICcuLi9wdWJsaWMnLFxuICAgIGVtcHR5T3V0RGlyOiBmYWxzZSxcbiAgICBsaWI6IHtcbiAgICAgIGVudHJ5OiAnc3JjL21haW4uanN4JyxcbiAgICAgIG5hbWU6ICdBSUNoYXRXaWRnZXQnLFxuICAgICAgZm9ybWF0czogWydpaWZlJ10sXG4gICAgICBmaWxlTmFtZTogKCkgPT4gJ3dpZGdldC5qcydcbiAgICB9LFxuICAgIHJvbGx1cE9wdGlvbnM6IHtcbiAgICAgIG91dHB1dDoge1xuICAgICAgICBhc3NldEZpbGVOYW1lczogKGFzc2V0SW5mbykgPT4ge1xuICAgICAgICAgIGlmIChhc3NldEluZm8ubmFtZSA9PT0gJ3N0eWxlLmNzcycpIHJldHVybiAnY3NzL3dpZGdldC5jc3MnO1xuICAgICAgICAgIHJldHVybiBhc3NldEluZm8ubmFtZTtcbiAgICAgICAgfVxuICAgICAgfVxuICAgIH1cbiAgfVxufSlcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBZ1MsU0FBUyxvQkFBb0I7QUFDN1QsT0FBTyxZQUFZO0FBRW5CLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQzFCLFNBQVMsQ0FBQyxPQUFPLENBQUM7QUFBQSxFQUNsQixPQUFPO0FBQUEsSUFDTCxRQUFRO0FBQUEsSUFDUixhQUFhO0FBQUEsSUFDYixLQUFLO0FBQUEsTUFDSCxPQUFPO0FBQUEsTUFDUCxNQUFNO0FBQUEsTUFDTixTQUFTLENBQUMsTUFBTTtBQUFBLE1BQ2hCLFVBQVUsTUFBTTtBQUFBLElBQ2xCO0FBQUEsSUFDQSxlQUFlO0FBQUEsTUFDYixRQUFRO0FBQUEsUUFDTixnQkFBZ0IsQ0FBQyxjQUFjO0FBQzdCLGNBQUksVUFBVSxTQUFTLFlBQWEsUUFBTztBQUMzQyxpQkFBTyxVQUFVO0FBQUEsUUFDbkI7QUFBQSxNQUNGO0FBQUEsSUFDRjtBQUFBLEVBQ0Y7QUFDRixDQUFDOyIsCiAgIm5hbWVzIjogW10KfQo=
