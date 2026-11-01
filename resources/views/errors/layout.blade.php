{{-- Autonome : ni base de données, ni session, ni fichiers Vite. Si le site est
     en panne, cette page doit quand même pouvoir s'afficher. --}}
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title') — Aissatou Store</title>
        <link rel="icon" href="/brand/favicon-32.png">
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: #FBF7F1; color: #17151B; font-family: Georgia, 'Times New Roman', serif; text-align: center; }
            main { max-width: 480px; }
            img { display: block; width: 56px; height: 56px; margin: 0 auto 32px; }
            h1 { margin: 0 0 16px; font-size: 2.25rem; font-weight: 400; line-height: 1.2; }
            p { margin: 0 0 32px; font-family: system-ui, sans-serif; font-size: 1rem; line-height: 1.6; color: #6E6672; }
            a { display: inline-block; padding: 14px 28px; background: #9F2D40; color: #fff; font-family: system-ui, sans-serif; font-size: .875rem; font-weight: 600; letter-spacing: .05em; text-decoration: none; }
            a:hover, a:focus { background: #17151B; }
        </style>
    </head>
    <body>
        <main>
            <img src="/brand/symbole-128.png" alt="Aissatou Store">
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <a href="/">Retour à la boutique</a>
        </main>
    </body>
</html>
