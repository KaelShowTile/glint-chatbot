import { h } from 'preact';
import { useState, useRef, useEffect } from 'preact/hooks';
import './style.css';

export default function App() {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([
    { id: 1, type: 'bot', text: 'Hello! How can I help you today?' }
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  };

  useEffect(() => {
    if (isOpen) {
      scrollToBottom();
    }
  }, [messages, isOpen]);

  const toggleOpen = () => setIsOpen(!isOpen);

  const handleSend = async () => {
    if (!input.trim() || isLoading) return;

    const userMessage = { id: Date.now(), type: 'user', text: input.trim() };
    setMessages(prev => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);

    try {
      // In production, this would point to the absolute URL of the backend API
      const apiUrl = window.glintChatbotConfig?.apiUrl || '/api/chat';
      const chatHistory = messages
        .filter(m => m.type !== 'system')
        .map(m => ({
          role: m.type === 'bot' ? 'assistant' : 'user',
          content: m.text
        }));
        
      chatHistory.push({ role: 'user', content: userMessage.text });

      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ messages: chatHistory })
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const data = await response.json();
      
      const botMessage = { id: Date.now() + 1, type: 'bot', text: data.reply };
      setMessages(prev => [...prev, botMessage]);

    } catch (error) {
      console.error('Error sending message:', error);
      const errorMessage = { id: Date.now() + 1, type: 'system', text: 'Sorry, there was an error connecting to the server.' };
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleSend();
    }
  };

  return (
    <div className="glint-chatbot-widget">
      {isOpen && (
        <div className="glint-chatbot-window">
          <div className="glint-chatbot-header">
            <span>Customer Support</span>
            <button className="glint-chatbot-close" onClick={toggleOpen} aria-label="Close Chat">
              &times;
            </button>
          </div>
          
          <div className="glint-chatbot-messages">
            {messages.map(msg => (
              <div key={msg.id} className={`glint-message ${msg.type}`}>
                {msg.text}
              </div>
            ))}
            {isLoading && (
              <div className="glint-message bot">
                <div className="glint-typing-indicator">
                  <div className="glint-typing-dot"></div>
                  <div className="glint-typing-dot"></div>
                  <div className="glint-typing-dot"></div>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          <div className="glint-chatbot-input-area">
            <input 
              type="text" 
              className="glint-chatbot-input"
              placeholder="Type your message..." 
              value={input}
              onInput={e => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              disabled={isLoading}
            />
            <button 
              className="glint-chatbot-send"
              onClick={handleSend}
              disabled={isLoading || !input.trim()}
              aria-label="Send Message"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2.01 21L23 12L2.01 3L2 10l15 2-15 2z" fill="currentColor"/>
              </svg>
            </button>
          </div>
        </div>
      )}

      {!isOpen && (
        <button className="glint-chatbot-toggle" onClick={toggleOpen} aria-label="Open Chat">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" fill="currentColor"/>
          </svg>
        </button>
      )}
    </div>
  );
}
