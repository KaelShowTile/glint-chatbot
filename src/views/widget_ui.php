<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-2xl font-bold mb-6">Widget UI Configuration</h2>
    
    <form action="<?php echo BASE_URL; ?>/admin/widget-ui" method="POST">
        
        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Frontend Appearance</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Chatbot Header Text</label>
                <input type="text" name="chatbot_header" value="<?php echo htmlspecialchars($settings['chatbot_header'] ?? ''); ?>" class="w-full border rounded px-3 py-2" placeholder="e.g., Customer Support">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Agent Name</label>
                <input type="text" name="chatbot_name" value="<?php echo htmlspecialchars($settings['chatbot_name'] ?? ''); ?>" class="w-full border rounded px-3 py-2" placeholder="e.g., AI Assistant">
                <p class="text-xs text-gray-500 mt-1">This name will appear next to the AI's chat bubble.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Avatar URL</label>
                <input type="url" name="chatbot_avatar_url" value="<?php echo htmlspecialchars($settings['chatbot_avatar_url'] ?? ''); ?>" class="w-full border rounded px-3 py-2" placeholder="https://example.com/avatar.png">
                <p class="text-xs text-gray-500 mt-1">Provide a full URL to an image. This will appear next to the AI's chat bubble.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Greeting Message</label>
                <textarea name="chatbot_greeting" class="w-full border rounded px-3 py-2 h-20" placeholder="Hello! How can I help you today?"><?php echo htmlspecialchars($settings['chatbot_greeting'] ?? ''); ?></textarea>
                <p class="text-xs text-gray-500 mt-1">This is the first message the user sees when they open the chat.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Toggle Button Custom HTML/SVG</label>
                <textarea name="toggle_icon_html" class="w-full border rounded px-3 py-2 h-20" placeholder="<svg>...</svg> or <img src='...'>"><?php echo htmlspecialchars($settings['toggle_icon_html'] ?? ''); ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Leave blank to use the default chat bubble icon. Otherwise, provide HTML to render inside the toggle button.</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Image Icon HTML/SVG</label>
                <textarea name="upload_icon_html" class="w-full border rounded px-3 py-2 h-20" placeholder="<svg>...</svg> or <img src='...'>"><?php echo htmlspecialchars($settings['upload_icon_html'] ?? ''); ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Leave blank to use the default paperclip icon (📎). Otherwise, provide HTML to render the image upload button.</p>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Quick Links</h3>
            <p class="text-sm text-gray-500 mb-4">Add quick navigation links to the bottom of the chat window.</p>
            
            <?php 
                $quickLinks = [];
                if (!empty($settings['quick_links'])) {
                    $quickLinks = json_decode($settings['quick_links'], true) ?: [];
                }
            ?>
            <div id="quick-links-container">
                <?php foreach ($quickLinks as $index => $link): ?>
                <div class="quick-link-row flex space-x-2 mb-2 items-center bg-gray-50 p-2 rounded border">
                    <input type="text" name="quick_links[<?php echo $index; ?>][icon]" value="<?php echo htmlspecialchars($link['icon'] ?? ''); ?>" placeholder="Icon (e.g. 🌟)" class="border rounded px-2 py-1 w-16 text-center">
                    <input type="text" name="quick_links[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars($link['title'] ?? ''); ?>" placeholder="Title (e.g. Daily Special)" class="border rounded px-3 py-1 flex-1">
                    <input type="url" name="quick_links[<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($link['url'] ?? ''); ?>" placeholder="URL (e.g. https://...)" class="border rounded px-3 py-1 flex-1">
                    <button type="button" class="btn-remove-link text-red-500 hover:text-red-700 font-bold px-2">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" id="btn-add-link" class="mt-2 text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 py-1 px-3 rounded">
                + Add Quick Link
            </button>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
            Save Settings
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('quick-links-container');
    const addBtn = document.getElementById('btn-add-link');
    let linkIndex = <?php echo count($quickLinks); ?>;

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'quick-link-row flex space-x-2 mb-2 items-center bg-gray-50 p-2 rounded border';
        row.innerHTML = `
            <input type="text" name="quick_links[${linkIndex}][icon]" placeholder="Icon (e.g. 🌟)" class="border rounded px-2 py-1 w-16 text-center">
            <input type="text" name="quick_links[${linkIndex}][title]" placeholder="Title (e.g. Daily Special)" class="border rounded px-3 py-1 flex-1">
            <input type="url" name="quick_links[${linkIndex}][url]" placeholder="URL (e.g. https://...)" class="border rounded px-3 py-1 flex-1">
            <button type="button" class="btn-remove-link text-red-500 hover:text-red-700 font-bold px-2">&times;</button>
        `;
        container.appendChild(row);
        linkIndex++;
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-link')) {
            e.target.closest('.quick-link-row').remove();
        }
    });
});
</script>
