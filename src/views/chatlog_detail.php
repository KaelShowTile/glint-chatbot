<style>
.rendered-markdown p:not(:last-child) { margin-bottom: 0.5rem; }
.rendered-markdown a { color: #2563eb; text-decoration: underline; }
.rendered-markdown a:hover { text-decoration: none; }
.rendered-markdown ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.5rem; }
.rendered-markdown ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.5rem; }
.rendered-markdown img { max-width: 100%; border-radius: 0.5rem; margin-top: 0.5rem; border: 1px solid #e5e7eb; }
.rendered-markdown strong { font-weight: 600; color: #111827; }
</style>

<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
        <div>
            <h2 class="text-2xl font-bold">Chat Log Details</h2>
            <p class="text-sm text-gray-500 font-mono mt-1">Session ID: <?php echo htmlspecialchars($sessionId); ?></p>
            <?php if (!empty($customerEmail) || !empty($customerAddress) || !empty($customerContactNumber)): ?>
                <div class="mt-4 bg-blue-50 border border-blue-200 p-3 rounded-lg">
                    <h3 class="text-sm font-semibold text-blue-800 mb-2">Customer Details</h3>
                    <?php if (!empty($customerEmail)): ?>
                        <p class="text-sm text-blue-900"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($customerEmail); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customerAddress)): ?>
                        <p class="text-sm text-blue-900 mt-1"><span class="font-medium">Address:</span> <?php echo htmlspecialchars($customerAddress); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($customerContactNumber)): ?>
                        <p class="text-sm text-blue-900 mt-1"><span class="font-medium">Contact Number:</span> <?php echo htmlspecialchars($customerContactNumber); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/chatlogs" class="text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded">
            &larr; Back to Logs
        </a>
    </div>

    <div class="bg-gray-50 p-4 rounded-lg border max-h-[700px] overflow-y-auto">
        <?php if (empty($messages)): ?>
            <p class="text-center text-gray-500 py-10">No messages found for this session or file is missing.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($messages as $msg): ?>
                    <?php 
                        $isUser = ($msg['type'] ?? '') === 'user';
                        $timestamp = $msg['timestamp'] ?? '';
                    ?>
                    <div class="flex flex-col <?php echo $isUser ? 'items-end' : 'items-start'; ?>">
                        <div class="text-xs text-gray-400 mb-1 mx-2">
                            <?php echo $isUser ? 'User' : 'AI Assistant'; ?> &bull; <?php echo htmlspecialchars($timestamp); ?>
                        </div>
                        <?php if ($isUser): ?>
                            <div class="bg-blue-600 text-white px-4 py-3 rounded-2xl max-w-2xl whitespace-pre-wrap text-sm shadow-sm">
                                <?php echo htmlspecialchars($msg['text'] ?? ''); ?>
                            </div>
                        <?php elseif (($msg['type'] ?? '') === 'bot_custom'): ?>
                            <div class="bg-white border text-gray-800 px-4 py-3 rounded-2xl max-w-2xl text-sm shadow-sm">
                                <?php echo $msg['html'] ?? ''; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white border text-gray-800 px-4 py-3 rounded-2xl max-w-2xl text-sm shadow-sm bot-message-container">
                                <div class="raw-markdown hidden"><?php echo htmlspecialchars($msg['text'] ?? ''); ?></div>
                                <div class="rendered-markdown"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.bot-message-container');
    containers.forEach(container => {
        const rawText = container.querySelector('.raw-markdown').textContent;
        const renderedDiv = container.querySelector('.rendered-markdown');
        renderedDiv.innerHTML = DOMPurify.sanitize(marked.parse(rawText));
    });
});
</script>
