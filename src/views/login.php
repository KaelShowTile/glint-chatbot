<div class="max-w-md mx-auto mt-20 bg-white p-8 border border-gray-300 rounded shadow-sm">
    <h1 class="text-2xl font-bold mb-6 text-center">Admin Login</h1>
    <form action="/admin/login" method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                Username
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="username" name="username" type="text" required>
        </div>
        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                Password
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="password" name="password" type="password" required>
        </div>
        <?php if ($enableWpLogin ?? false): ?>
        <div class="mb-6 flex items-center">
            <input type="checkbox" id="wp_login" name="wp_login" value="1" class="mr-2">
            <label for="wp_login" class="text-sm text-gray-700">Login with WordPress Admin Account</label>
        </div>
        <?php endif; ?>
        <div class="flex items-center justify-between">
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" type="submit">
                Sign In
            </button>
        </div>
    </form>
</div>
