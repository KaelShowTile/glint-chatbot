<div class="max-w-md mx-auto mt-20 bg-white p-8 border border-gray-300 rounded shadow-sm">
    <h1 class="text-2xl font-bold mb-6 text-center">System Initialization</h1>
    <p class="mb-4 text-gray-600 text-sm">Please set up your global administrator account to continue.</p>
    <form action="/admin/init" method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                Admin Username
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="username" name="username" type="text" required>
        </div>
        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                Admin Password
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="password" name="password" type="password" required>
        </div>
        <div class="flex items-center justify-between">
            <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" type="submit">
                Complete Setup
            </button>
        </div>
    </form>
</div>
