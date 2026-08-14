<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>{{ config('app.name', 'NexaISP') }} API documentation</title>
    <link rel="icon" href="{{ asset('brand/nexa-isp.svg') }}" type="image/svg+xml">
    <script src="https://unpkg.com/@stoplight/elements@9.0.24/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@9.0.24/styles.min.css">
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: #f8faf9;
        }

        elements-api {
            display: block;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <elements-api
        apiDescriptionUrl="{{ route('docs.api.spec', [], false) }}"
        layout="sidebar"
        router="hash"
    ></elements-api>
</body>
</html>
