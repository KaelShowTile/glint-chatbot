import { h } from 'preact';
import { useState, useRef, useEffect } from 'preact/hooks';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import './style.css';

export default function App() {
  const [isOpen, setIsOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('voice'); // 'voice' or 'text'
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const [isContinuousListening, setIsContinuousListening] = useState(false);
  const mediaRecorderRef = useRef(null);
  const audioChunksRef = useRef([]);
  const micVADRef = useRef(null);
  const abortControllerRef = useRef(null);
  const currentAudioRef = useRef(null);
  const [sessionId, setSessionId] = useState('');
  const [config, setConfig] = useState({
    header: 'Customer Support',
    name: 'AI Assistant',
    avatar: '',
    greeting: 'Hello! How can I help you today?',
    quickLinks: []
  });
  const messagesEndRef = useRef(null);
  const initialized = useRef(false);

  // Initialize session and fetch config/history
  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;

    // Get or create session ID
    let sid = localStorage.getItem('glint_chat_session_id');
    if (!sid) {
      sid = 'session_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
      localStorage.setItem('glint_chat_session_id', sid);
    }
    setSessionId(sid);

    // Determine base API URL
    let defaultApiUrl = '/api';
    try {
      const scriptTag = document.querySelector('script[src$="widget.js"]');
      if (scriptTag && scriptTag.src) {
        const url = new URL(scriptTag.src);
        defaultApiUrl = url.origin + url.pathname.replace('/widget.js', '/api');
      }
    } catch (e) { }

    const baseUrl = window.glintChatbotConfig?.apiUrl ? window.glintChatbotConfig.apiUrl.replace('/chat', '') : defaultApiUrl;

    // Fetch config
    fetch(`${baseUrl}/widget/config`)
      .then(res => res.json())
      .then(data => {
        if (data && data.header) {
          setConfig(data);
        }
      })
      .catch(err => console.error('Failed to fetch widget config:', err))
      .finally(() => {
        // Fetch history
        fetch(`${baseUrl}/chat/history?session_id=${sid}`)
          .then(res => res.json())
          .then(data => {
            if (data && data.messages && data.messages.length > 0) {
              // Convert backend log format to frontend state format
              const history = data.messages.map((m, idx) => ({
                id: Date.now() + idx,
                type: m.type,
                text: m.text,
                html: m.html
              }));
              setMessages(history);
            } else {
              // No history, use greeting from config (which was just fetched or defaults)
              // Since state updates are batched/async, we need to rely on the latest fetched data or the default
              setMessages(prev => {
                if (prev.length === 0) {
                  // Re-fetch config state directly to avoid closure issues
                  let greeting = 'Hello! How can I help you today?';
                  // To be completely safe with state, we update greeting when config resolves
                  return [{ id: 1, type: 'bot', text: '...' }]; // temporary placeholder replaced below
                }
                return prev;
              });
            }
          })
          .catch(err => console.error('Failed to fetch history:', err));
      });
  }, []);

  // Update greeting once config is loaded if no history
  useEffect(() => {
    if (messages.length === 1 && messages[0].text === '...') {
      setMessages([{ id: 1, type: 'bot', text: config.greeting }]);
    }
  }, [config, messages]);

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
      let defaultApiUrl = '/api/chat';
      try {
        const scriptTag = document.querySelector('script[src$="widget.js"]');
        if (scriptTag && scriptTag.src) {
          const url = new URL(scriptTag.src);
          defaultApiUrl = url.origin + url.pathname.replace('/widget.js', '/api/chat');
        }
      } catch (e) { }

      // In production, this would point to the absolute URL of the backend API
      const apiUrl = window.glintChatbotConfig?.apiUrl || defaultApiUrl;
      const chatHistory = messages
        .filter(m => m.type !== 'system' && m.type !== 'bot_custom' && !!m.text)
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
        body: JSON.stringify({ messages: chatHistory, session_id: sessionId })
      });

      if (!response.ok) {
        if (response.status === 0 || response.type === 'error') {
          throw new Error(`Network response was not ok`);
        }
        const errorText = await response.text();
        throw new Error(`Network response was not ok (Status: ${response.status}). Details: ${errorText}`);
      }

      const data = await response.json();

      const botMessage = { id: Date.now() + 1, type: 'bot', text: data.reply };
      setMessages(prev => [...prev, botMessage]);

      if (data.execute_js) {
        try {
          const widgetObj = {
            websiteUrl: config.website_url || '',
            addMessage: (htmlContent) => {
              const customMsg = { id: Date.now() + Math.random(), type: 'bot_custom', html: htmlContent };
              setMessages(prev => [...prev, customMsg]);
              fetch(`${apiUrl}/log`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, type: 'bot_custom', html: htmlContent })
              }).catch(err => console.error("Error logging fallback:", err));
            }
          };
          const dynamicFunction = new Function('args', 'widget', data.execute_js);
          dynamicFunction(data.execute_args || {}, widgetObj);
        } catch (e) {
          console.error("Error executing JS:", e);
        }
      }

    } catch (error) {
      console.error('Error sending message:', error);
      const errorMessage = { id: Date.now() + 1, type: 'system', text: 'Sorry, there was an error connecting to the server.' };
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  // Helper function to convert Float32Array to WAV Blob
  const float32ToWav = (float32Array, sampleRate = 16000) => {
    const numChannels = 1;
    const numSamples = float32Array.length;
    const bytesPerSample = 2;
    const blockAlign = numChannels * bytesPerSample;
    const byteRate = sampleRate * blockAlign;
    const dataSize = numSamples * blockAlign;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);

    const writeString = (view, offset, string) => {
      for (let i = 0; i < string.length; i++) {
        view.setUint8(offset + i, string.charCodeAt(i));
      }
    };

    writeString(view, 0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeString(view, 8, 'WAVE');
    writeString(view, 12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, numChannels, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, byteRate, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, bytesPerSample * 8, true);
    writeString(view, 36, 'data');
    view.setUint32(40, dataSize, true);

    let offset = 44;
    for (let i = 0; i < numSamples; i++) {
      let s = Math.max(-1, Math.min(1, float32Array[i]));
      view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
      offset += 2;
    }

    return new Blob([view], { type: 'audio/wav' });
  };

  const loadVadScripts = async () => {
    if (window.vad) return window.vad;

    // Load ort.js first (required by vad-web script)
    await new Promise((resolve, reject) => {
      if (window.ort) return resolve();
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.14.0/dist/ort.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    // Load vad-web
    await new Promise((resolve, reject) => {
      if (window.vad) return resolve();
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/@ricky0123/vad-web@0.0.19/dist/bundle.min.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return window.vad;
  };

  const handleAudioSend = async (audioBlob) => {
    setIsLoading(true);

    // Add a temporary user message placeholder for UI feedback
    const tempUserMsgId = Date.now();
    setMessages(prev => [...prev, { id: tempUserMsgId, type: 'user', text: '🎤 (Voice Message)' }]);

    abortControllerRef.current = new AbortController();

    try {
      let defaultApiUrl = '/api/chat';
      try {
        const scriptTag = document.querySelector('script[src$="widget.js"]');
        if (scriptTag && scriptTag.src) {
          const url = new URL(scriptTag.src);
          defaultApiUrl = url.origin + url.pathname.replace('/widget.js', '/api/chat');
        }
      } catch (e) { }

      const apiUrl = window.glintChatbotConfig?.apiUrl || defaultApiUrl;
      const chatHistory = messages
        .filter(m => m.type !== 'system' && m.type !== 'bot_custom' && m.id !== tempUserMsgId && !!m.text)
        .map(m => ({
          role: m.type === 'bot' ? 'assistant' : 'user',
          content: m.text
        }));

      const formData = new FormData();
      formData.append('messages', JSON.stringify(chatHistory));
      formData.append('session_id', sessionId);
      formData.append('audio', audioBlob, 'recording.wav');

      const response = await fetch(apiUrl, {
        method: 'POST',
        body: formData,
        signal: abortControllerRef.current.signal
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const data = await response.json();

      // Replace the temporary message with the actual transcribed text if provided
      if (data.user_text) {
        setMessages(prev => prev.map(m => m.id === tempUserMsgId ? { ...m, text: data.user_text } : m));
      }

      const botMessage = { id: Date.now() + 1, type: 'bot', text: data.reply };
      setMessages(prev => [...prev, botMessage]);

      if (data.audio) {
        const audioUrl = `data:audio/mp3;base64,${data.audio}`;
        const audio = new Audio(audioUrl);
        currentAudioRef.current = audio;
        audio.play().catch(e => console.error("Audio playback failed:", e));
        audio.onended = () => {
          if (currentAudioRef.current === audio) {
            currentAudioRef.current = null;
          }
        };
      }

      if (data.execute_js) {
        try {
          const widgetObj = {
            websiteUrl: config.website_url || '',
            addMessage: (htmlContent) => {
              const customMsg = { id: Date.now() + Math.random(), type: 'bot_custom', html: htmlContent };
              setMessages(prev => [...prev, customMsg]);
              fetch(`${apiUrl}/log`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, type: 'bot_custom', html: htmlContent })
              }).catch(err => console.error("Error logging fallback:", err));
            }
          };
          const dynamicFunction = new Function('args', 'widget', data.execute_js);
          dynamicFunction(data.execute_args || {}, widgetObj);
        } catch (e) {
          console.error("Error executing JS:", e);
        }
      }

    } catch (error) {
      if (error.name === 'AbortError') {
        console.log('Voice fetch aborted by user interruption.');
        // Remove the temporary voice message if we aborted
        setMessages(prev => prev.filter(m => m.id !== tempUserMsgId));
      } else {
        console.error('Error sending audio:', error);
        const errorMessage = { id: Date.now() + 1, type: 'system', text: 'Sorry, there was an error processing your voice.' };
        setMessages(prev => [...prev, errorMessage]);
      }
    } finally {
      setIsLoading(false);
      abortControllerRef.current = null;
    }
  };

  const startConversation = async () => {
    try {
      setIsLoading(true); // Show loading indicator while fetching VAD
      const vad = await loadVadScripts();

      const stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        }
      });

      const myvad = await vad.MicVAD.new({
        stream: stream,
        workletURL: 'https://cdn.jsdelivr.net/npm/@ricky0123/vad-web@0.0.19/dist/vad.worklet.bundle.min.js',
        modelURL: 'https://cdn.jsdelivr.net/npm/@ricky0123/vad-web@0.0.19/dist/silero_vad.onnx',
        ortWasmUrl: 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.14.0/dist/ort-wasm.wasm',
        ortWasmSimdUrl: 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.14.0/dist/ort-wasm-simd.wasm',
        onSpeechStart: () => {
          console.log("Speech started");
          // Barge-in: pause playing audio
          if (currentAudioRef.current) {
            currentAudioRef.current.pause();
            currentAudioRef.current = null;
          }
          // Barge-in: abort pending fetch
          if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
          }
        },
        onSpeechEnd: (audioData) => {
          console.log("Speech ended");
          const wavBlob = float32ToWav(audioData, 16000);
          handleAudioSend(wavBlob);
        }
      });


      micVADRef.current = myvad;
      myvad.start();

      setIsContinuousListening(true);
      setIsLoading(false);
    } catch (err) {
      console.error("Error starting conversation:", err);
      setIsContinuousListening(false);
      setIsLoading(false);
      alert("Failed to start voice activity detection. Please allow microphone access.");
    }
  };

  const stopConversation = () => {
    if (micVADRef.current) {
      micVADRef.current.pause();
      micVADRef.current = null;
    }
    if (currentAudioRef.current) {
      currentAudioRef.current.pause();
      currentAudioRef.current = null;
    }
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      abortControllerRef.current = null;
    }
    setIsContinuousListening(false);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleSend();
    }
  };

  return (
    <div className="glint-chatbot-widget">
      {isOpen && (
        <div class="glint-chatbot-window">
          <div className="glint-chatbot-header">
            <span>{config.header}</span>
            <button className="glint-chatbot-close" onClick={toggleOpen} aria-label="Close Chat">
              &times;
            </button>
          </div>

          <div className="glint-chatbot-tabs">
            <button
              className={`glint-tab ${activeTab === 'voice' ? 'active' : ''}`}
              onClick={() => setActiveTab('voice')}
            >
              Voice
            </button>
            <button
              className={`glint-tab ${activeTab === 'text' ? 'active' : ''}`}
              onClick={() => setActiveTab('text')}
            >
              Text
            </button>
          </div>

          <div className="glint-chatbot-messages">
            {messages.map(msg => (
              <div key={msg.id} className={`glint-message-wrapper ${msg.type}`}>
                {msg.type === 'bot' && (
                  <div className="glint-bot-profile">
                    {config.avatar ? (
                      <img src={config.avatar} alt="AI Avatar" className="glint-bot-avatar" />
                    ) : (
                      <div className="glint-bot-avatar-placeholder">AI</div>
                    )}
                  </div>
                )}
                <div className="glint-message-content">
                  {msg.type === 'bot' && config.name && (
                    <div className="glint-bot-name">{config.name}</div>
                  )}
                  <div className={`glint-message ${msg.type}`}>
                    {msg.type === 'bot' ? (
                      <div
                        className="glint-markdown"
                        dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(marked.parse(msg.text)) }}
                      />
                    ) : msg.type === 'bot_custom' ? (
                      <div
                        className="glint-custom-html"
                        dangerouslySetInnerHTML={{ __html: msg.html }}
                      />
                    ) : (
                      msg.text
                    )}
                  </div>
                </div>
              </div>
            ))}
            {isLoading && (
              <div className="glint-message-wrapper bot">
                <div className="glint-bot-profile">
                  {config.avatar ? (
                    <img src={config.avatar} alt="AI Avatar" className="glint-bot-avatar" />
                  ) : (
                    <div className="glint-bot-avatar-placeholder">AI</div>
                  )}
                </div>
                <div className="glint-message-content">
                  {config.name && <div className="glint-bot-name">{config.name}</div>}
                  <div className="glint-message bot">
                    <div className="glint-typing-indicator">
                      <div className="glint-typing-dot"></div>
                      <div className="glint-typing-dot"></div>
                      <div className="glint-typing-dot"></div>
                    </div>
                  </div>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {config.quickLinks && config.quickLinks.length > 0 && (
            <div className="glint-quick-links">
              {config.quickLinks.map((link, idx) => (
                <a key={idx} href={link.url} target="_blank" rel="noopener noreferrer" className="glint-quick-link">
                  {link.icon && <span className="glint-quick-link-icon">{link.icon}</span>}
                  <span className="glint-quick-link-title">{link.title}</span>
                </a>
              ))}
            </div>
          )}

          <div className="glint-chatbot-input-area">
            {activeTab === 'text' ? (
              <>
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
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.01 21L23 12L2.01 3L2 10l15 2-15 2z" fill="currentColor" />
                  </svg>
                </button>
              </>
            ) : (
              <div className="glint-chatbot-voice-area">
                {!isContinuousListening ? (
                  <button
                    key="start-btn"
                    className="glint-voice-btn"
                    onClick={startConversation}
                    disabled={isLoading}
                  >
                    <span className="glint-voice-idle-text">▶️ Tap to Start Conversation</span>
                  </button>
                ) : (
                  <button
                    key="stop-btn"
                    className={`glint-voice-btn ${!isLoading ? 'recording' : ''}`}
                    onClick={stopConversation}
                  >
                    <span className="glint-voice-recording-text">
                      ⏹ {!isLoading ? 'Listening for your voice...' : 'Answering...'}
                    </span>
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {!isOpen && (
        <button className="glint-chatbot-toggle" onClick={toggleOpen} aria-label="Open Chat">
          {config.toggle_icon_html ? (
            <div dangerouslySetInnerHTML={{ __html: config.toggle_icon_html }} />
          ) : (
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" fill="currentColor" />
            </svg>
          )}
        </button>
      )}
    </div>
  );
}
