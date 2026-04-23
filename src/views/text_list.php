<div class="bg-white p-6 rounded shadow-sm border border-gray-200 mb-6">
    <h2 class="text-xl font-bold mb-4">Add General Information</h2>
    <form action="<?php echo BASE_URL; ?>/admin/text" method="POST">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Content (e.g., Return policy, Contact info)</label>
            <textarea name="content" class="w-full border rounded px-3 py-2 h-32" required></textarea>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
            Add Information
        </button>
    </form>
</div>

<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-xl font-bold mb-4">Existing Information</h2>
    <div class="space-y-4">
        <?php foreach ($items ?? [] as $item): ?>
            <div class="border rounded p-4">
                <form action="<?php echo BASE_URL; ?>/admin/text" method="POST" class="mb-2">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <textarea name="content" class="w-full border rounded px-3 py-2 h-24 mb-2" required><?php echo htmlspecialchars($item['content']); ?></textarea>
                    <div class="flex justify-end space-x-2">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded text-sm">Update</button>
                        <a href="<?php echo BASE_URL; ?>/admin/text/delete/<?php echo $item['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-4 py-1 rounded text-sm" onclick="return confirm('Are you sure you want to delete this?');">Delete</a>
                    </div>
                </form>
                <div class="text-xs text-gray-500">Vector ID: <?php echo $item['qdrant_id']; ?> | Added: <?php echo $item['created_at']; ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <p class="text-gray-500">No general information added yet.</p>
        <?php endif; ?>
    </div>
</div>
