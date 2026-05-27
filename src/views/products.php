<div class="bg-white p-6 rounded shadow-sm border border-gray-200 mb-6">
    <h2 class="text-xl font-bold mb-4">Product Synchronization</h2>
    
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm text-gray-600 mb-1">Products are automatically synchronized from your feed URL.</p>
            <p class="text-xs text-gray-500">Current Feed URL: <?php echo htmlspecialchars($feedUrl ?? 'Not set'); ?></p>
        </div>
        <div class="flex flex-col items-end space-y-2">
            <div class="flex space-x-2">
                <form action="<?php echo BASE_URL; ?>/admin/products/delete-all" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL products from the local database and the vector database? This cannot be undone.');">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded">
                        Delete All
                    </button>
                </form>
                <button type="button" id="syncBtn" onclick="startSync()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                    Sync Now
                </button>
            </div>
            
            <div id="syncProgressContainer" class="hidden w-full max-w-md mt-4">
                <div class="flex justify-between text-xs mb-1">
                    <span id="syncStatusText" class="font-semibold text-blue-600">Preparing Sync...</span>
                    <span id="syncCountText" class="text-gray-600">0 / 0</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="syncProgressBar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function startSync() {
    const btn = document.getElementById('syncBtn');
    const progressContainer = document.getElementById('syncProgressContainer');
    const statusText = document.getElementById('syncStatusText');
    const countText = document.getElementById('syncCountText');
    const progressBar = document.getElementById('syncProgressBar');

    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    progressContainer.classList.remove('hidden');
    statusText.innerText = 'Downloading and parsing XML feed...';
    progressBar.style.width = '5%';

    try {
        // Step 1: Prepare Sync
        let res = await fetch('<?php echo BASE_URL; ?>/admin/products/sync/prepare', { method: 'POST' });
        let data = await res.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to prepare sync');
        }

        const total = data.data.total;
        if (total === 0) {
            statusText.innerText = 'No new or updated products to sync.';
            progressBar.style.width = '100%';
            setTimeout(() => location.reload(), 1500);
            return;
        }

        statusText.innerText = 'Syncing vectors to Qdrant...';
        let processed = 0;
        let isSyncing = true;

        // Step 2: Loop chunks
        while (isSyncing) {
            res = await fetch('<?php echo BASE_URL; ?>/admin/products/sync/chunk', { method: 'POST' });
            data = await res.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to process chunk');
            }

            processed = data.data.processed;
            let currentTotal = data.data.total;
            let percentage = Math.min(100, Math.round((processed / currentTotal) * 100));
            
            countText.innerText = `${processed} / ${currentTotal}`;
            progressBar.style.width = `${percentage}%`;

            if (data.data.status === 'complete') {
                isSyncing = false;
            }
        }

        // Step 3: Finalize Sync
        statusText.innerText = 'Finalizing...';
        await fetch('<?php echo BASE_URL; ?>/admin/products/sync/finalize', { method: 'POST' });
        
        statusText.innerText = 'Sync Complete!';
        statusText.classList.replace('text-blue-600', 'text-green-600');
        progressBar.classList.replace('bg-blue-600', 'bg-green-600');
        
        setTimeout(() => location.reload(), 1500);
        
    } catch (err) {
        statusText.innerText = 'Error: ' + err.message;
        statusText.classList.replace('text-blue-600', 'text-red-600');
        progressBar.classList.replace('bg-blue-600', 'bg-red-600');
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}
</script>

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
