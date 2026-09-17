<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Seller Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen flex font-sans antialiased text-text-main overflow-hidden bg-surface relative">

    <x-auth.login-form 
        title="Seller Portal" 
        subtitle="Manage your store and orders"
        :submitRoute="route('login.post')"
        :registerRoute="route('seller.register')"
        registerText="Apply as a Seller"
    />

    @livewireScripts
</body>
</html>