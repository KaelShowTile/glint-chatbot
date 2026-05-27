<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-2xl font-bold mb-4">Chat Logs</h2>
    <p class="text-gray-600 mb-6">View all conversation histories between users and the AI Assistant.</p>
    
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b text-left">Session ID</th>
                    <th class="py-2 px-4 border-b text-left">Messages</th>
                    <th class="py-2 px-4 border-b text-left">Started At</th>
                    <th class="py-2 px-4 border-b text-left">Last Activity</th>
                    <th class="py-2 px-4 border-b text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions ?? [] as $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-2 px-4 border-b text-sm font-mono text-gray-600"><?php echo htmlspecialchars($s['session_id']); ?></td>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($s['message_count']); ?></td>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($s['created_at']); ?></td>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($s['updated_at']); ?></td>
                    <td class="py-2 px-4 border-b text-sm text-center">
                        <a href="<?php echo BASE_URL; ?>/admin/chatlogs/<?php echo urlencode($s['session_id']); ?>" class="text-blue-600 hover:text-blue-800 font-medium">View Thread</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sessions)): ?>
                <tr>
                    <td colspan="5" class="py-4 text-center text-gray-500">No chat sessions recorded yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
