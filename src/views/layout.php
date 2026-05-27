<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Customer Service Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal text-gray-800">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php if (isset($_SESSION['user'])): ?>
            <div class="bg-gray-900 w-full md:w-64 flex flex-col text-white">
                <div class="p-4 text-2xl font-bold border-b border-gray-800">ST AI Chatbot</div>
                <nav class="flex-1 px-2 py-4 space-y-2">
                    <a href="<?php echo BASE_URL; ?>/admin/text"
                        class="block px-4 py-2 rounded hover:bg-gray-800">Knowledge</a>
                    <a href="<?php echo BASE_URL; ?>/admin/qa" class="block px-4 py-2 rounded hover:bg-gray-800">Q&A</a>
                    <a href="<?php echo BASE_URL; ?>/admin/products"
                        class="block px-4 py-2 rounded hover:bg-gray-800">Products</a>
                    <a href="<?php echo BASE_URL; ?>/admin/widget-ui"
                        class="block px-4 py-2 rounded hover:bg-gray-800">Widget UI</a>
                    <a href="<?php echo BASE_URL; ?>/admin/chatlogs" class="block px-4 py-2 rounded hover:bg-gray-800">Chat Logs</a>
                    <a href="<?php echo BASE_URL; ?>/admin/agent-functions" class="block px-4 py-2 rounded hover:bg-gray-800">Agent Functions</a>
                    <a href="<?php echo BASE_URL; ?>/admin/settings"
                        class="block px-4 py-2 rounded hover:bg-gray-800">Settings</a>
                </nav>
                <div class="p-4 border-t border-gray-800">
                    <a href="<?php echo BASE_URL; ?>/admin/logout"
                        class="block px-4 py-2 bg-red-600 rounded text-center hover:bg-red-700">Logout</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex-1 p-6 md:p-10">
            <?php if (isset($error) && $error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($success) && $success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"
                    role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php echo $content ?? ''; ?>
        </div>
    </div>
</body>

</html>