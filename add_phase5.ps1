# add_phase5.ps1
# Adds Open Graph meta tags + share.js script to Auger HTML pages

$baseUrl = 'https://auger-renovations.onrender.com'

# Per-page metadata
$pages = @{
    'index.html' = @{
        title = 'Auger Renovations — Rénovations résidentielles à Montréal'
        desc  = 'Cuisines, salles de bains, sous-sols, planchers, patios et pergolas. Rénovations de qualité à Vaudreuil-Dorion et Montréal.'
        image = 'assets/images/ARenovation-ModernKitchen_Index.png'
    }
    'accueil.html' = @{
        title = 'Auger Renovations — Accueil'
        desc  = 'Entreprise de rénovation résidentielle à Vaudreuil-Dorion. Cuisines, salles de bains, sous-sols.'
        image = 'assets/images/ARenovation-Cuisine.png'
    }
    'realisations.html' = @{
        title = 'Nos réalisations — Auger Renovations'
        desc  = 'Portfolio complet de nos projets : cuisines modernes, salles de bains luxueuses, sous-sols aménagés.'
        image = 'assets/images/ARenovation-Cuisine.png'
    }
    'services.html' = @{
        title = 'Nos services — Auger Renovations'
        desc  = 'Rénovation de cuisines, salles de bains, sous-sols, planchers, patios et pergolas.'
        image = 'assets/images/ARenovation-Cuisine.png'
    }
    'cuisene.html' = @{
        title = 'Rénovation de cuisines — Auger Renovations'
        desc  = 'Cuisines modernes sur mesure : comptoirs, armoires, électroménagers encastrés.'
        image = 'assets/images/ARenovation-Cuisine.png'
    }
    'salle_de_bains.html' = @{
        title = 'Rénovation de salles de bains — Auger Renovations'
        desc  = 'Salles de bains modernes : douches italiennes, vanités, céramiques.'
        image = 'assets/images/ARenovation_SalledeBains.png'
    }
    'sous_sol.html' = @{
        title = 'Aménagement de sous-sols — Auger Renovations'
        desc  = 'Sous-sols transformés en espaces de vie : salons, bureaux, chambres.'
        image = 'assets/images/ARenovation-Soul-sol.png'
    }
    'plancher.html' = @{
        title = 'Pose de planchers — Auger Renovations'
        desc  = 'Planchers de bois, céramique, vinyle. Installation professionnelle.'
        image = 'assets/images/ARenovation-Plancher.png'
    }
    'patios_pergolas.html' = @{
        title = 'Patios et pergolas — Auger Renovations'
        desc  = 'Patios en pavé, pergolas en bois, terrasses sur mesure.'
        image = 'assets/images/ARenovation-Patio-Pergolas.png'
    }
    'contact.html' = @{
        title = 'Contact — Auger Renovations'
        desc  = 'Demandez votre soumission gratuite pour votre projet de rénovation.'
        image = 'assets/images/Auger_Logo.png'
    }
    'explaining.html' = @{
        title = 'Comment ça marche — Auger Renovations'
        desc  = 'Notre processus de soumission et de réalisation, étape par étape.'
        image = 'assets/images/ARenovation-Cuisine.png'
    }
}

foreach ($file in $pages.Keys) {
    $path = Join-Path $PSScriptRoot $file
    if (-not (Test-Path $path)) { Write-Host "SKIP: $file (not found)" -ForegroundColor Yellow; continue }

    $page = $pages[$file]
    $content = Get-Content $path -Raw -Encoding UTF8

    $modified = $false

    # ---- Add OG meta tags (if not present) ----
    if ($content -notmatch 'og:title') {
        $ogBlock = @"

<!-- Open Graph / Social Media Preview -->
<meta property="og:title" content="$($page.title)">
<meta property="og:description" content="$($page.desc)">
<meta property="og:image" content="$baseUrl/$($page.image)">
<meta property="og:url" content="$baseUrl/$file">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Auger Renovations">
<meta property="og:locale" content="fr_CA">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="$($page.title)">
<meta name="twitter:description" content="$($page.desc)">
<meta name="twitter:image" content="$baseUrl/$($page.image)">

"@
        $content = $content -replace '</head>', "$ogBlock</head>"
        $modified = $true
        Write-Host "  + OG tags added" -ForegroundColor Green
    } else {
        Write-Host "  (OG tags already present)" -ForegroundColor DarkGray
    }

    # ---- Add share.js (if not present) ----
    if ($content -notmatch 'assets/js/share\.js') {
        $content = $content -replace '</body>', '<script src="assets/js/share.js"></script>' + "`n</body>"
        $modified = $true
        Write-Host "  + share.js added" -ForegroundColor Green
    } else {
        Write-Host "  (share.js already present)" -ForegroundColor DarkGray
    }

    if ($modified) {
        Set-Content $path -Value $content -Encoding UTF8 -NoNewline
        Write-Host "✓ $file updated" -ForegroundColor Cyan
    } else {
        Write-Host "  $file unchanged" -ForegroundColor DarkGray
    }
    Write-Host ""
}

Write-Host "=== Phase 5 injection complete ===" -ForegroundColor Magenta