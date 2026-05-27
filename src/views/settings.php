<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-2xl font-bold mb-6">System Settings</h2>

    <form action="<?php echo BASE_URL; ?>/admin/settings" method="POST">

        <!-- API Configuration -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">General Settings</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                <input type="url" name="website_url"
                    value="<?php echo htmlspecialchars($settings['website_url'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2" placeholder="https://yourwebsite.com">
                <p class="text-xs text-gray-500 mt-1">The base URL of your website, used by Agent Functions to construct API requests.</p>
            </div>

            <h3 class="text-lg font-semibold border-b pb-2 mb-4 mt-8">API Configuration</h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Google Gemini API Key</label>
                <input type="password" name="gemini_api_key"
                    value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Groq API Key (Optional)</label>
                <input type="password" name="groq_api_key"
                    value="<?php echo htmlspecialchars($settings['groq_api_key'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">LLM Provider</label>
                <select id="llm_provider" name="llm_provider" class="w-full border rounded px-3 py-2">
                    <option value="gemini" <?php echo ($settings['llm_provider'] ?? '') == 'gemini' ? 'selected' : ''; ?>>
                        Google Gemini (Default)</option>
                    <option value="groq" <?php echo ($settings['llm_provider'] ?? '') == 'groq' ? 'selected' : ''; ?>>Groq
                        (Llama/Mixtral)</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Embedding Model Name</label>
                <div class="flex">
                    <select id="embedding_model_name" name="embedding_model_name"
                        class="w-full border rounded px-3 py-2 bg-gray-50"
                        data-selected="<?php echo htmlspecialchars($settings['embedding_model_name'] ?? ''); ?>">
                        <option value="">Loading models...</option>
                    </select>
                    <button type="button" id="btn_refresh_embedding"
                        class="ml-2 bg-gray-200 hover:bg-gray-300 px-4 rounded border text-sm font-medium">Refresh</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Embedding always uses Gemini API. The default and recommended
                    model is gemini-embedding-001.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">LLM Model Name</label>
                <div class="flex">
                    <select id="llm_model_name" name="llm_model_name" class="w-full border rounded px-3 py-2 bg-gray-50"
                        data-selected="<?php echo htmlspecialchars($settings['llm_model_name'] ?? ''); ?>">
                        <option value="">Select provider first...</option>
                    </select>
                    <button type="button" id="btn_refresh_models"
                        class="ml-2 bg-gray-200 hover:bg-gray-300 px-4 rounded border text-sm font-medium">Refresh</button>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Prompt</label>
                <textarea name="custom_prompt" class="w-full border rounded px-3 py-2 h-24"
                    placeholder="Enter any additional instructions for the AI here..."><?php echo htmlspecialchars($settings['custom_prompt'] ?? ''); ?></textarea>
                <p class="text-xs text-gray-500 mt-1">This text will be appended to the AI's core system prompt to guide
                    its tone or behavior.</p>
            </div>

            <h3 class="text-lg font-semibold border-b pb-2 mb-4 mt-8">Voice / TTS Configuration</h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">TTS Provider</label>
                <select id="tts_provider" name="tts_provider" class="w-full border rounded px-3 py-2">
                    <option value="gemini" <?php echo ($settings['tts_provider'] ?? '') == 'gemini' ? 'selected' : ''; ?>>
                        Google Gemini Native Audio</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Currently supports Gemini models natively generating audio.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">TTS Model Name</label>
                <div class="flex">
                    <select id="tts_model_name" name="tts_model_name" class="w-full border rounded px-3 py-2 bg-gray-50"
                        data-selected="<?php echo htmlspecialchars($settings['tts_model_name'] ?? ''); ?>">
                        <option value="">Select provider first...</option>
                    </select>
                    <button type="button" id="btn_refresh_tts_models"
                        class="ml-2 bg-gray-200 hover:bg-gray-300 px-4 rounded border text-sm font-medium">Refresh</button>
                </div>
            </div>

            <h3 class="text-lg font-semibold border-b pb-2 mb-4 mt-8">Vector Database Configuration</h3>

            <div class="mb-4 flex space-x-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qdrant URL</label>
                    <input type="text" name="qdrant_url"
                        value="<?php echo htmlspecialchars($settings['qdrant_url'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2" placeholder="https://xxx.qdrant.tech">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qdrant API Key</label>
                    <input type="password" name="qdrant_api_key"
                        value="<?php echo htmlspecialchars($settings['qdrant_api_key'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        <!-- WordPress Integration -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Authentication</h3>
            <div class="mb-4 flex items-center">
                <input type="checkbox" id="enable_wp_login" name="enable_wp_login" value="1" <?php echo ($settings['enable_wp_login'] ?? '') == '1' ? 'checked' : ''; ?>
                    class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded"
                    onchange="document.getElementById('wp_path_wrapper').style.display = this.checked ? 'block' : 'none'">
                <label for="enable_wp_login" class="text-sm font-medium text-gray-700">Allow login with WordPress Admin
                    account</label>
            </div>
            <div id="wp_path_wrapper" class="mb-4"
                style="display: <?php echo ($settings['enable_wp_login'] ?? '') == '1' ? 'block' : 'none'; ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">WordPress Root Path</label>
                <input type="text" name="wp_path" value="<?php echo htmlspecialchars($settings['wp_path'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2" placeholder="/var/www/html/wordpress">
                <p class="text-xs text-gray-500 mt-1">Absolute path to your WordPress installation (where wp-config.php
                    is located).</p>
            </div>
        </div>

        <!-- Escalate to Human / Email -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Escalation & Email Settings (PHPMailer)</h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Fallback Message</label>
                <textarea name="escalation_message" class="w-full border rounded px-3 py-2 h-20"
                    placeholder="Please contact us directly..."><?php echo htmlspecialchars($settings['escalation_message'] ?? ''); ?></textarea>
            </div>

            <div class="mb-4 flex items-center">
                <input type="checkbox" id="enable_escalate_email" name="enable_escalate_email" value="1" <?php echo ($settings['enable_escalate_email'] ?? '') == '1' ? 'checked' : ''; ?>
                    class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="enable_escalate_email" class="text-sm font-medium text-gray-700">Ask user if they want to
                    contact staff and send summary to Admin Email</label>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email Address</label>
                <input type="email" name="admin_email"
                    value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                    <input type="text" name="smtp_host"
                        value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                    <input type="text" name="smtp_port"
                        value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2" placeholder="e.g., 587 or 465">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                    <input type="text" name="smtp_user"
                        value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                    <input type="password" name="smtp_pass"
                        value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>"
                        class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Encryption</label>
                    <select name="smtp_encryption" class="w-full border rounded px-3 py-2">
                        <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo ($settings['smtp_encryption'] ?? '') == '' ? 'selected' : ''; ?>>None
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Product Feed -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Product Sync</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">XML Product Feed URL</label>
                <input type="url" name="product_feed_url"
                    value="<?php echo htmlspecialchars($settings['product_feed_url'] ?? ''); ?>"
                    class="w-full border rounded px-3 py-2" placeholder="https://example.com/feed.xml">
                <p class="text-xs text-gray-500 mt-1">Products will be automatically synchronized daily at 4:00 AM.</p>
            </div>
        </div>

        <!-- Integration Code -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Widget Integration</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Embed Code</label>
                <textarea readonly class="w-full border rounded px-3 py-2 h-32 bg-gray-50 font-mono text-sm">&lt;link rel="stylesheet" href="https://yourdomain.com/css/widget.css"&gt;
&lt;script src="https://yourdomain.com/widget.js" defer&gt;&lt;/script&gt;
&lt;div id="ai-chat-widget"&gt;&lt;/div&gt;</textarea>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
            Save Settings
        </button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const providerSelect = document.getElementById('llm_provider');
        const modelSelect = document.getElementById('llm_model_name');
        const refreshBtn = document.getElementById('btn_refresh_models');

        const embeddingModelSelect = document.getElementById('embedding_model_name');
        const refreshEmbeddingBtn = document.getElementById('btn_refresh_embedding');

        const ttsProviderSelect = document.getElementById('tts_provider');
        const ttsModelSelect = document.getElementById('tts_model_name');
        const refreshTtsBtn = document.getElementById('btn_refresh_tts_models');

        function loadModels(selectEl, provider, type, btnEl) {
            selectEl.innerHTML = '<option value="">Loading models...</option>';
            selectEl.disabled = true;
            if (btnEl) btnEl.disabled = true;

            fetch(`<?php echo BASE_URL; ?>/admin/api/models?provider=${provider}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    selectEl.innerHTML = '';
                    if (data.error) {
                        selectEl.innerHTML = `<option value="">Error: ${data.error}</option>`;
                    } else if (data.models && data.models.length > 0) {
                        const selected = selectEl.getAttribute('data-selected');
                        let found = false;
                        data.models.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.id;
                            option.textContent = model.name;
                            if (model.id === selected) {
                                option.selected = true;
                                found = true;
                            }
                            selectEl.appendChild(option);
                        });
                        if (!found && data.models.length > 0) {
                            selectEl.selectedIndex = 0;
                        }
                    } else {
                        selectEl.innerHTML = '<option value="">No models found or API key missing.</option>';
                    }
                })
                .catch(err => {
                    selectEl.innerHTML = '<option value="">Failed to load models</option>';
                })
                .finally(() => {
                    selectEl.disabled = false;
                    if (btnEl) btnEl.disabled = false;
                });
        }

        providerSelect.addEventListener('change', () => loadModels(modelSelect, providerSelect.value, 'generate', refreshBtn));
        refreshBtn.addEventListener('click', () => loadModels(modelSelect, providerSelect.value, 'generate', refreshBtn));
        refreshEmbeddingBtn.addEventListener('click', () => loadModels(embeddingModelSelect, 'gemini', 'embed', refreshEmbeddingBtn));
        ttsProviderSelect.addEventListener('change', () => loadModels(ttsModelSelect, ttsProviderSelect.value, 'generate', refreshTtsBtn));
        refreshTtsBtn.addEventListener('click', () => loadModels(ttsModelSelect, ttsProviderSelect.value, 'generate', refreshTtsBtn));

        // Initial load
        loadModels(modelSelect, providerSelect.value, 'generate', refreshBtn);
        loadModels(embeddingModelSelect, 'gemini', 'embed', refreshEmbeddingBtn);
        loadModels(ttsModelSelect, ttsProviderSelect.value, 'generate', refreshTtsBtn);
    });
</script>