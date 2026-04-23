import { h, render } from 'preact';
import App from './App.jsx';

// Create a mount point if it doesn't exist
let mountPoint = document.getElementById('glint-chatbot-root');

if (!mountPoint) {
  mountPoint = document.createElement('div');
  mountPoint.id = 'glint-chatbot-root';
  document.body.appendChild(mountPoint);
}

// Render the widget
render(<App />, mountPoint);
