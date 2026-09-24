<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Design system preview</title>
    <link rel="stylesheet" href="{{ $tokenStylesheet }}">
    @vite('resources/scss/public.scss')
</head>
<body class="pa-public">
<main class="container py-5">
    <p class="pa-eyebrow">Design system</p>
    <h1>Heading level one</h1>
    <p class="lead">Lead paragraph — Probha Aurora CMS uses runtime design tokens, so branding changes apply without a rebuild.</p>
    <h2>Heading level two</h2>
    <p>Body text with an <a href="#preview">inline link</a>. The quick brown fox jumps over the lazy dog. <span class="text-body-secondary">Muted supporting text.</span></p>
    <h3>Heading level three</h3>

    <section class="my-4" aria-label="Buttons">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary">Primary</button>
            <button class="btn btn-secondary">Secondary</button>
            <button class="btn btn-accent">Accent</button>
            <button class="btn btn-outline-primary">Outline</button>
            <button class="btn btn-link">Link button</button>
            <button class="btn btn-primary" disabled>Disabled</button>
        </div>
    </section>

    <section class="row g-4 my-2" aria-label="Cards">
        @foreach (['Environmental education', 'Youth reporters', 'Green schools'] as $i => $title)
            <div class="col-md-4">
                <article class="card pa-card-public h-100">
                    <div class="pa-card-public__media" aria-hidden="true"></div>
                    <div class="card-body">
                        <span class="pa-badge-public">Program</span>
                        <h4 class="h5 mt-2"><a href="#preview" class="stretched-link">{{ $title }}</a></h4>
                        <p class="mb-0 text-body-secondary">A short summary of the item that appears in cards and listings.</p>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="my-4" aria-label="Alerts and badges">
        <div class="alert alert-success" role="status">Success message.</div>
        <div class="alert alert-warning">Warning message.</div>
        <div class="alert alert-danger">Error message.</div>
    </section>

    <section class="my-4 pa-section-dark p-4 rounded-3" aria-label="Dark section">
        <h2 class="h4">Dark section</h2>
        <p class="mb-3">White text on the dark background token.</p>
        <button class="btn btn-accent">Accent on dark</button>
    </section>

    <section class="my-4" aria-label="Form">
        <form class="row g-3">
            <div class="col-md-6">
                <label for="demo-name" class="form-label">Name</label>
                <input id="demo-name" class="form-control" placeholder="Your name">
            </div>
            <div class="col-md-6">
                <label for="demo-email" class="form-label">E-mail</label>
                <input id="demo-email" class="form-control is-invalid" value="not-an-email" aria-invalid="true" aria-describedby="demo-email-error">
                <div id="demo-email-error" class="invalid-feedback">Enter a valid e-mail address.</div>
            </div>
        </form>
    </section>
</main>
</body>
</html>
