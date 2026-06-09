<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>eZWay TV React SPA</title>
    <script src="https://imasdk.googleapis.com/js/sdkloader/ima3.js"></script>
    @vite('resources/react/main.tsx')
</head>
<body>
    <div id="react-modernization-root"></div>
</body>
</html>
