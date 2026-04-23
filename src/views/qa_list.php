<div class="bg-white p-6 rounded shadow-sm border border-gray-200 mb-6">
    <h2 class="text-xl font-bold mb-4">Add Q&A Pair</h2>
    <form action="/admin/qa" method="POST">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Question / Query</label>
            <input type="text" name="content" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Answer</label>
            <textarea name="answer" class="w-full border rounded px-3 py-2 h-24" required></textarea>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
            Add Q&A
        </button>
    </form>
</div>

<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-xl font-bold mb-4">Existing Q&A Pairs</h2>
    <div class="space-y-4">
        <?php foreach ($items ?? [] as $item): ?>
            <div class="border rounded p-4">
                <form action="/admin/qa" method="POST" class="mb-2">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Question</label>
                        <input type="text" name="content" value="<?php echo htmlspecialchars($item['content']); ?>" class="w-full border rounded px-3 py-2" required>
                    </div>
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Answer</label>
                        <textarea name="answer" class="w-full border rounded px-3 py-2 h-20" required><?php echo htmlspecialchars($item['answer']); ?></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded text-sm">Update</button>
                        <a href="/admin/qa/delete/<?php echo $item['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-4 py-1 rounded text-sm" onclick="return confirm('Are you sure you want to delete this Q&A?');">Delete</a>
                    </div>
                </form>
                <div class="text-xs text-gray-500">Vector ID: <?php echo $item['qdrant_id']; ?> | Added: <?php echo $item['created_at']; ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <p class="text-gray-500">No Q&A pairs added yet.</p>
        <?php endif; ?>
    </div>
</div>
