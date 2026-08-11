<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Reports</h1>
</div>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <form method="GET" action="<?php echo BASE_URL; ?>/admin/reports" class="flex items-end space-x-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                Apply Filter
            </button>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
    <!-- Chart 1: Daily Sessions -->
    <div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Daily Chat Sessions</h2>
        <div class="relative h-64 mb-4">
            <canvas id="sessionsChart"></canvas>
        </div>
        <table class="w-full text-sm text-left text-gray-500 mt-auto border-t">
            <tfoot>
                <tr class="font-bold text-gray-900 bg-gray-50">
                    <td class="px-4 py-3">Total Sessions (Selected Period)</td>
                    <td class="px-4 py-3 text-right"><?php echo $totalSessions; ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Chart 2: Daily Average Messages -->
    <div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Average Messages per Session</h2>
        <div class="relative h-64 mb-4">
            <canvas id="messagesChart"></canvas>
        </div>
        <table class="w-full text-sm text-left text-gray-500 mt-auto border-t">
            <tfoot>
                <tr class="font-bold text-gray-900 bg-gray-50">
                    <td class="px-4 py-3">True Average (Selected Period)</td>
                    <td class="px-4 py-3 text-right"><?php echo $avgMessages; ?> messages/session</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Chart 3: Agent Functions -->
<div class="bg-white p-6 rounded-lg shadow-md mb-8 flex flex-col">
    <h2 class="text-xl font-semibold mb-4 text-gray-800">Agent Function Calls</h2>
    <div class="relative h-80 mb-4">
        <canvas id="functionsChart"></canvas>
    </div>
    <table class="w-full text-sm text-left text-gray-500 mt-auto border-t">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 hidden md:table-header-group">
            <tr>
                <th scope="col" class="px-4 py-3">Function Name</th>
                <th scope="col" class="px-4 py-3 text-right">Total Calls</th>
            </tr>
        </thead>
        <tfoot>
            <?php foreach ($totalFunctionLogs as $fnName => $count): ?>
            <tr class="font-bold text-gray-900 bg-gray-50 border-b last:border-0">
                <td class="px-4 py-3">Total <?php echo htmlspecialchars($fnName); ?></td>
                <td class="px-4 py-3 text-right"><?php echo $count; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($totalFunctionLogs)): ?>
            <tr class="font-bold text-gray-900 bg-gray-50">
                <td class="px-4 py-3" colspan="2">No function calls in this period.</td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Prepare Data
    const dailySessionsData = <?php echo json_encode($dailySessions); ?>;
    const dailyMessagesData = <?php echo json_encode($dailyMessages); ?>;
    const functionLogsData = <?php echo json_encode($functionLogs); ?>;

    // Helper: generate a list of all dates between start and end (to fill in gaps)
    const startDate = new Date("<?php echo $startDate; ?>");
    const endDate = new Date("<?php echo $endDate; ?>");
    const labels = [];
    
    // Normalize to midnight UTC for iteration
    let currentDate = new Date(Date.UTC(startDate.getFullYear(), startDate.getMonth(), startDate.getDate()));
    const lastDate = new Date(Date.UTC(endDate.getFullYear(), endDate.getMonth(), endDate.getDate()));
    
    while (currentDate <= lastDate) {
        labels.push(currentDate.toISOString().split('T')[0]);
        currentDate.setUTCDate(currentDate.getUTCDate() + 1);
    }

    // Process Sessions Data
    const sessionsMap = {};
    dailySessionsData.forEach(item => {
        sessionsMap[item.log_date] = item.session_count;
    });
    const sessionsDataset = labels.map(date => sessionsMap[date] || 0);

    // Process Messages Data
    const messagesMap = {};
    dailyMessagesData.forEach(item => {
        messagesMap[item.log_date] = parseFloat(item.avg_messages).toFixed(1);
    });
    const messagesDataset = labels.map(date => messagesMap[date] || 0);

    // Process Function Logs Data
    // We need a dataset for each unique function
    const uniqueFunctions = <?php echo json_encode(array_values($allFuncNames)); ?>;
    const functionsColors = [
        'rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'
    ];
    
    const functionsDatasets = uniqueFunctions.map((fnName, index) => {
        const fnDataMap = {};
        functionLogsData.filter(item => item.function_name === fnName).forEach(item => {
            fnDataMap[item.log_date] = item.call_count;
        });
        
        return {
            label: fnName,
            data: labels.map(date => fnDataMap[date] || 0),
            backgroundColor: functionsColors[index % functionsColors.length],
            borderColor: functionsColors[index % functionsColors.length].replace('0.7', '1'),
            borderWidth: 1
        };
    });

    // Render Charts
    window.onload = function() {
        // 1. Sessions Chart (Line)
        new Chart(document.getElementById('sessionsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Sessions',
                    data: sessionsDataset,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // 2. Average Messages Chart (Bar)
        new Chart(document.getElementById('messagesChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avg Messages/Session',
                    data: messagesDataset,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 3. Agent Functions Chart (Stacked Bar)
        new Chart(document.getElementById('functionsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: functionsDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    };
</script>
