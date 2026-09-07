<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{-- Inline style for the loading overlay, kept independent of the
             Tailwind/app.css build so it renders correctly even before
             those assets finish loading. --}}
        <style>
            #initial-loading-overlay {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                background-color: oklch(1 0 0);
                font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            }

            html.dark #initial-loading-overlay {
                background-color: oklch(0.145 0 0);
            }

            #initial-loading-overlay .spinner {
                width: 2.5rem;
                height: 2.5rem;
                border: 3px solid oklch(0.85 0 0);
                border-top-color: oklch(0.5 0.2 260);
                border-radius: 50%;
                animation: initial-loading-spin 0.8s linear infinite;
            }

            html.dark #initial-loading-overlay .spinner {
                border-color: oklch(0.35 0 0);
                border-top-color: oklch(0.7 0.2 260);
            }

            #initial-loading-overlay p {
                color: oklch(0.4 0 0);
                font-size: 0.95rem;
                margin: 0;
            }

            html.dark #initial-loading-overlay p {
                color: oklch(0.75 0 0);
            }

            @keyframes initial-loading-spin {
                to { transform: rotate(360deg); }
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
<link rel="icon" type="image/png" href="/LOGOS.png" />


        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Shown until Vue mounts content into #app above, then removed
             automatically by the MutationObserver script below. --}}
        <div id="initial-loading-overlay">
            <div class="spinner"></div>
            <p>Page is loading, please wait a while…</p>
        </div>

        <script>
            (function () {
                var appEl = document.getElementById('app');
                var overlay = document.getElementById('initial-loading-overlay');

                if (!appEl || !overlay) return;

                function removeOverlay() {
                    if (overlay && overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }

                // Vue/Inertia mounts by injecting content into #app — once
                // that happens, the overlay's job is done.
                if (appEl.childElementCount > 0) {
                    removeOverlay();
                    return;
                }

                var observer = new MutationObserver(function () {
                    if (appEl.childElementCount > 0) {
                        removeOverlay();
                        observer.disconnect();
                    }
                });

                observer.observe(appEl, { childList: true });

                // Safety net: never let the overlay block the page forever
                // if something goes wrong with mounting.
                setTimeout(function () {
                    removeOverlay();
                    observer.disconnect();
                }, 15000);
            })();
        </script>
    </body>
</html>