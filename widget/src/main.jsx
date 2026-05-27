import { h, render } from 'preact';
import App from './App.jsx';

function initWidget() {
  let mountPoint = document.getElementById('ai-chat-widget');
  
  if (!mountPoint) {
    mountPoint = document.createElement('div');
    mountPoint.id = 'ai-chat-widget';
    document.body.appendChild(mountPoint);
  }

  // Render the widget
  render(<App />, mountPoint);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initWidget);
} else {
  initWidget();
}
