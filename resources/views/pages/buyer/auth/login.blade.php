<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen flex font-sans antialiased text-text-main overflow-hidden bg-surface relative">

    <!-- Removed the wrapping div here so the two-column layout isn't broken -->
    <x-auth.login-form />

    @livewireScripts
</body>
</html>