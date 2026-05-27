<div class="bg-white p-6 rounded shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Agent Functions</h2>
        <button onclick="openAddModal()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">
            + Add Function
        </button>
    </div>

    <p class="text-gray-600 mb-6 text-sm">
        Agent Functions allow the AI to execute real Javascript code on your website. Define when the AI should call a
        function, and provide the exact Javascript snippet to run.
    </p>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="py-3 px-4 border-b">Function Name / Call ID</th>
                    <th class="py-3 px-4 border-b">Description (Prompt)</th>
                    <th class="py-3 px-4 border-b">Parameters (JSON Schema)</th>
                    <th class="py-3 px-4 border-b">JS Execution Code</th>
                    <th class="py-3 px-4 border-b w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
                <?php if (empty($functions)): ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-gray-500">No agent functions added yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($functions as $fn): ?>
                        <tr>
                            <td class="py-3 px-4 align-top">
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($fn['name']); ?></div>
                                <div class="text-xs text-gray-500 font-mono mt-1">
                                    <?php echo htmlspecialchars($fn['call_id']); ?></div>
                            </td>
                            <td class="py-3 px-4 align-top text-gray-600 text-xs whitespace-pre-wrap max-w-xs">
                                <?php echo htmlspecialchars($fn['description']); ?></td>
                            <td class="py-3 px-4 align-top">
                                <?php if (!empty($fn['parameters_schema'])): ?>
                                    <pre
                                        class="text-xs bg-gray-50 p-2 rounded border border-gray-100 overflow-x-auto max-w-xs font-mono text-green-800"><?php echo htmlspecialchars($fn['parameters_schema']); ?></pre>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 align-top">
                                <pre
                                    class="text-xs bg-gray-50 p-2 rounded border border-gray-100 overflow-x-auto max-w-sm font-mono text-blue-800"><?php echo htmlspecialchars($fn['js_code']); ?></pre>
                            </td>
                            <td class="py-3 px-4 align-top">
                                <button onclick="editFunction(<?php echo htmlspecialchars(json_encode($fn), ENT_QUOTES, 'UTF-8'); ?>)"
                                    class="text-blue-500 hover:text-blue-700 font-medium text-xs mr-3">Edit</button>
                                <button onclick="deleteFunction(<?php echo $fn['id']; ?>)"
                                    class="text-red-500 hover:text-red-700 font-medium text-xs">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-lg font-bold">Add New Agent Function</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="addForm" onsubmit="addFunction(event)">
            <input type="hidden" id="fn_id" value="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Function Name</label>
                <input type="text" id="fn_name" required class="w-full border rounded px-3 py-2"
                    placeholder="e.g., Open Cart Modal">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Call ID</label>
                <input type="text" id="fn_call_id" required pattern="[a-zA-Z0-9_]+"
                    class="w-full border rounded px-3 py-2 font-mono text-sm" placeholder="e.g., open_cart">
                <p class="text-xs text-gray-500 mt-1">Letters, numbers, and underscores only. No spaces.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description (Instructions for AI)</label>
                <textarea id="fn_description" required class="w-full border rounded px-3 py-2 h-24 text-sm"
                    placeholder="Tell the AI exactly when to use this function. e.g., 'Call this function when the user asks to view their shopping cart or proceed to checkout.'"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Parameters Schema (Optional)</label>
                <textarea id="fn_parameters_schema"
                    class="w-full border rounded px-3 py-2 h-24 font-mono text-xs bg-gray-50"
                    placeholder='{"type": "object", "properties": {"product_ids": {"type": "string"}}}'></textarea>
                <p class="text-xs text-gray-500 mt-1">Valid JSON Schema object. Leave empty if no parameters are needed.
                </p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">JavaScript Execution Code</label>
                <textarea id="fn_js_code" required
                    class="w-full border rounded px-3 py-2 h-32 font-mono text-sm bg-gray-50"
                    placeholder="window.location.href = '/cart';&#10;// or&#10;document.getElementById('cart-modal').style.display = 'block';"></textarea>
                <p class="text-xs text-gray-500 mt-1">This code will be executed natively in the user's browser when the
                    AI triggers this function.</p>
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()"
                    class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="submitBtn"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Function</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('fn_id').value = '';
        document.getElementById('addForm').reset();
        document.getElementById('modalTitle').innerText = 'Add New Agent Function';
        document.getElementById('submitBtn').innerText = 'Save Function';
        document.getElementById('addModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function editFunction(fn) {
        document.getElementById('fn_id').value = fn.id;
        document.getElementById('fn_name').value = fn.name;
        document.getElementById('fn_call_id').value = fn.call_id;
        document.getElementById('fn_description').value = fn.description;
        document.getElementById('fn_parameters_schema').value = fn.parameters_schema || '';
        document.getElementById('fn_js_code').value = fn.js_code;

        document.getElementById('modalTitle').innerText = 'Edit Agent Function';
        document.getElementById('submitBtn').innerText = 'Save Changes';
        document.getElementById('addModal').classList.remove('hidden');
    }

    function addFunction(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const originalText = btn.innerText;
        btn.innerText = 'Saving...';
        btn.disabled = true;

        const data = {
            id: document.getElementById('fn_id').value,
            name: document.getElementById('fn_name').value,
            call_id: document.getElementById('fn_call_id').value,
            description: document.getElementById('fn_description').value,
            parameters_schema: document.getElementById('fn_parameters_schema').value,
            js_code: document.getElementById('fn_js_code').value
        };

        fetch('<?php echo BASE_URL; ?>/admin/agent-functions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to add function');
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Request failed');
                btn.innerText = originalText;
                btn.disabled = false;
            });
    }

    function deleteFunction(id) {
        if (!confirm('Are you sure you want to delete this function?')) return;

        fetch('<?php echo BASE_URL; ?>/admin/agent-functions/' + id, {
            method: 'DELETE'
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to delete');
                }
            });
    }
</script>