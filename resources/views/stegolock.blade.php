<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>StegoLock — Standalone App</title>
    @vite('resources/js/stegolock-spa/main.tsx')
</head>
<body class="h-full antialiased">
    <div id="stegolock-root" class="h-full"></div>
</body>
</html>
