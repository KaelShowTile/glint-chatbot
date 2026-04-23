<div class="bg-white p-6 rounded shadow-sm border border-gray-200 mb-6">
    <h2 class="text-xl font-bold mb-4">Product Synchronization</h2>
    
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm text-gray-600 mb-1">Products are automatically synchronized from your feed URL.</p>
            <p class="text-xs text-gray-500">Current Feed URL: <?php echo htmlspecialchars($feedUrl ?? 'Not set'); ?></p>
        </div>
        <form action="<?php echo BASE_URL; ?>/admin/products/sync" method="POST">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                Sync Now
            </button>
        </form>
    </div>
</div>

<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <h2 class="text-xl font-bold mb-4">Synchronized Products (<?php echo count($products ?? []); ?>)</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b text-left">SKU</th>
                    <th class="py-2 px-4 border-b text-left">Product ID</th>
                    <th class="py-2 px-4 border-b text-left">Last Updated</th>
                    <th class="py-2 px-4 border-b text-left">Qdrant ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products ?? [] as $p): ?>
                <tr>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($p['sku']); ?></td>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($p['product_id']); ?></td>
                    <td class="py-2 px-4 border-b text-sm"><?php echo htmlspecialchars($p['updated_at']); ?></td>
                    <td class="py-2 px-4 border-b text-sm text-gray-500 font-mono text-xs"><?php echo htmlspecialchars($p['qdrant_id']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="4" class="py-4 text-center text-gray-500">No products synchronized yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
