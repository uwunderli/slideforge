#!/usr/bin/env php
<?php
/**
 * Erzeugt seed/feature-tour/{de,en,fr,it,rm}/ aus Übersetzungen + gemeinsamen assets/.
 * Aufruf: php scripts/build_feature_tour.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$seedRoot = $base . '/seed/feature-tour';
$assetsDir = $seedRoot . '/assets';
$seedAssetId = 'feature0';

$languages = [
    'de' => ['token' => 'slideforge-tour-de', 'title' => 'SlideForge Feature-Tour'],
    'en' => ['token' => 'slideforge-tour', 'title' => 'SlideForge Feature Tour'],
    'fr' => ['token' => 'slideforge-tour-fr', 'title' => 'SlideForge – Visite guidée'],
    'it' => ['token' => 'slideforge-tour-it', 'title' => 'SlideForge – Tour delle funzioni'],
    'rm' => ['token' => 'slideforge-tour-rm', 'title' => 'SlideForge – Turn da funcziuns'],
];

function langLinksMd(): string
{
    $demoBase = 'https://slideforge.service7.ch';
    $tourUrl = static fn (string $token): string => $demoBase . '/view.php?token=' . $token;

    return '[**DE**](' . $tourUrl('slideforge-tour-de') . ') · [**EN**](' . $tourUrl('slideforge-tour') . ') · [**FR**](' . $tourUrl('slideforge-tour-fr') . ') · [**IT**](' . $tourUrl('slideforge-tour-it') . ') · [**RM**](' . $tourUrl('slideforge-tour-rm') . ')';
}

/** @return array<string, array<string, string>> */
function tourStrings(): array
{
    $langLinksMd = langLinksMd();
    $repoLinkMd = '[github.com/uwunderli/slideforge](https://github.com/uwunderli/slideforge)';
    $demoLinkMd = '[slideforge.service7.ch](https://slideforge.service7.ch/)';

    return [
        'de' => [
            'nav_title' => 'Navigation',
            'nav_body' => "**Weiter:** Pfeiltaste → oder Leertaste\n**Zurück:** Pfeiltaste ←\n**Übersicht:** Esc · **Vollbild:** F\n\nAnimierte Folien: Leertaste oder → für jeden Schritt",
            'title' => 'SlideForge',
            'subtitle' => 'Self-hosted Editor für reveal.js-Präsentationen',
            'hosting_title' => 'Einfaches Hosting',
            'hosting_body' => "**Keine Datenbank** — alles in JSON unter `data/`.\n\nLäuft mit **PHP 8.2+** auf nginx, Apache oder IIS — ohne Composer.\n\nBackup = Ordner kopieren.",
            'dash_title' => 'Dashboard',
            'dash_caption' => 'Eigene und geteilte Präsentationen auf einen Blick',
            'share_title' => 'Multi-User & Teilen',
            'share_body' => "- Rollen: **Administrator** & **Editor**\n- Teilen mit Lese- oder Bearbeitungsrecht\n- **Öffentliche Links** ohne Login\n- Einladungslinks optional",
            'editor_title' => 'Visueller Canvas-Editor',
            'editor_body' => "Objekte verschieben, skalieren, drehen — Folien **1920×1080**.\n\nEigenschaften-Panel, Ebenen, Auto-Speichern.",
            'editor_caption' => 'Konva.js-Editor mit Werkzeugleisten und Eigenschaften',
            'content_title' => 'Reicher Folieninhalt',
            'content_body' => "**Text** mit Markdown, Formen, Bilder, Video, Audio.\n\nHintergründe: Farbe, Verlauf, Bild, Video.",
            'anim_title' => 'Schritt-für-Schritt-Animationen',
            'anim_body' => "Jedes Objekt kann animiert werden — diese Folie zeigt es beim Durchklicken.",
            'tpl_title' => 'Vorlagen & Markenfarben',
            'tpl_body' => "- **Folienvorlagen** und Textstile\n- **Markenfarbpalette** (editierbar)\n- **Pixabay** für Stock-Medien (optional)",
            'tpl_caption' => 'Folienvorlagen, Textvorlagen und Farbpalette',
            'present_title' => 'Präsentationsmodus',
            'present_body' => "Echtes **reveal.js** — nicht nur Screenshots.\n\nLive-Sync, Laserpointer, Notizen, Timer.",
            'present_caption' => 'Vollbild-Präsentation wie beim Vortrag',
            'export_title' => 'Export',
            'export_body' => "- **Offline-HTML** (Einzeldatei)\n- **PDF**\n- **PPTX / ODP** *(mit Einschränkungen — Layout und Animationen werden vereinfacht)*\n- Öffentlicher **View-Link**",
            'oss_title' => 'Open Source · MIT',
            'oss_body' => $repoLinkMd,
            'langs_title' => 'Weitere Sprachen',
            'try_title' => 'SlideForge ausprobieren',
            'try_body' => "**Demo** (Reset alle 12h)\n{$demoLinkMd}\n\n**admin** / **admin** · **editor** / **editor**",
            'try_end' => 'Danke fürs Durchklicken!',
        ],
        'en' => [
            'nav_title' => 'Navigation',
            'nav_body' => "**Next:** Right arrow or Space\n**Previous:** Left arrow\n**Overview:** Esc · **Fullscreen:** F\n\nAnimated slides: Space or → for each step",
            'title' => 'SlideForge',
            'subtitle' => 'Self-hosted editor for reveal.js presentations',
            'hosting_title' => 'Simple hosting',
            'hosting_body' => "**No database** — JSON files under `data/`.\n\nRuns on **PHP 8.2+** with nginx, Apache, or IIS — no Composer.\n\nBackup = copy a folder.",
            'dash_title' => 'Dashboard',
            'dash_caption' => 'Your decks and shared presentations at a glance',
            'share_title' => 'Multi-user & sharing',
            'share_body' => "- Roles: **Administrator** & **Editor**\n- Share with view or edit rights\n- **Public links** without login\n- Invite-only registration optional",
            'editor_title' => 'Visual canvas editor',
            'editor_body' => "Drag, resize and rotate objects on **1920×1080** slides.\n\nProperties panel, layers, auto-save.",
            'editor_caption' => 'Konva.js editor with toolbars and properties',
            'content_title' => 'Rich slide content',
            'content_body' => "**Text** with Markdown, shapes, images, video, audio.\n\nBackgrounds: color, gradient, image, video.",
            'anim_title' => 'Step-by-step animations',
            'anim_body' => "Every object can animate — click through this slide to see it.",
            'tpl_title' => 'Templates & brand kit',
            'tpl_body' => "- **Slide templates** and text styles\n- Editable **brand colors**\n- **Pixabay** stock media (optional API key)",
            'tpl_caption' => 'Slide templates, text styles and color palette',
            'present_title' => 'Presentation mode',
            'present_body' => "Real **reveal.js** output — not screenshots.\n\nLive sync, laser pointer, notes, timer.",
            'present_caption' => 'Fullscreen presentation view',
            'export_title' => 'Export anywhere',
            'export_body' => "- **Offline HTML** (single file)\n- **PDF**\n- **PPTX / ODP** *(with limitations — layout and animations are simplified)*\n- Public **view link**",
            'oss_title' => 'Open source · MIT',
            'oss_body' => $repoLinkMd,
            'langs_title' => 'Other languages',
            'try_title' => 'Try SlideForge',
            'try_body' => "**Live demo** (resets every 12h)\n{$demoLinkMd}\n\n**admin** / **admin** · **editor** / **editor**",
            'try_end' => 'Thanks for watching this tour!',
        ],
        'fr' => [
            'nav_title' => 'Navigation',
            'nav_body' => "**Suivant :** Flèche droite ou Espace\n**Précédent :** Flèche gauche\n**Aperçu :** Échap · **Plein écran :** F\n\nDiapos animées : Espace ou → pour chaque étape",
            'title' => 'SlideForge',
            'subtitle' => 'Éditeur auto-hébergé pour présentations reveal.js',
            'hosting_title' => 'Hébergement simple',
            'hosting_body' => "**Sans base de données** — fichiers JSON dans `data/`.\n\n**PHP 8.2+** sur nginx, Apache ou IIS — sans Composer.\n\nSauvegarde = copier un dossier.",
            'dash_title' => 'Tableau de bord',
            'dash_caption' => 'Vos présentations et celles partagées',
            'share_title' => 'Multi-utilisateur & partage',
            'share_body' => "- Rôles : **Administrateur** & **Éditeur**\n- Partage en lecture ou édition\n- **Liens publics** sans connexion\n- Inscription sur invitation",
            'editor_title' => 'Éditeur visuel',
            'editor_body' => "Déplacer, redimensionner, pivoter — diapos **1920×1080**.\n\nPanneau de propriétés, calques, enregistrement auto.",
            'editor_caption' => 'Éditeur Konva.js avec barres d’outils',
            'content_title' => 'Contenu riche',
            'content_body' => "**Texte** Markdown, formes, images, vidéo, audio.\n\nFonds : couleur, dégradé, image, vidéo.",
            'anim_title' => 'Animations pas à pas',
            'anim_body' => "Chaque objet peut s’animer — cliquez pour avancer.",
            'tpl_title' => 'Modèles & charte',
            'tpl_body' => "- **Modèles de diapos** et styles de texte\n- **Couleurs de marque** éditables\n- Médias **Pixabay** (optionnel)",
            'tpl_caption' => 'Modèles, styles texte et palette',
            'present_title' => 'Mode présentation',
            'present_body' => "Vrai **reveal.js** — pas une capture d’écran.\n\nSync live, pointeur laser, notes, minuterie.",
            'present_caption' => 'Présentation plein écran',
            'export_title' => 'Export',
            'export_body' => "- **HTML hors ligne** · **PDF**\n- **PPTX / ODP** *(avec limitations — mise en page et animations simplifiées)*\n- Lien public **view**",
            'oss_title' => 'Open source · MIT',
            'oss_body' => $repoLinkMd,
            'langs_title' => 'Autres langues',
            'try_title' => 'Essayer SlideForge',
            'try_body' => "**Démo** (reset 12h)\n{$demoLinkMd}\n\n**admin** / **admin**",
            'try_end' => 'Merci d’avoir suivi cette visite !',
        ],
        'it' => [
            'nav_title' => 'Navigazione',
            'nav_body' => "**Avanti:** Freccia destra o Spazio\n**Indietro:** Freccia sinistra\n**Panoramica:** Esc · **Schermo intero:** F\n\nDiapositive animate: Spazio o → per ogni passo",
            'title' => 'SlideForge',
            'subtitle' => 'Editor self-hosted per presentazioni reveal.js',
            'hosting_title' => 'Hosting semplice',
            'hosting_body' => "**Nessun database** — JSON in `data/`.\n\n**PHP 8.2+** con nginx, Apache o IIS — senza Composer.\n\nBackup = copiare una cartella.",
            'dash_title' => 'Dashboard',
            'dash_caption' => 'Presentazioni proprie e condivise',
            'share_title' => 'Multi-utente & condivisione',
            'share_body' => "- Ruoli: **Amministratore** & **Editor**\n- Condivisione lettura o modifica\n- **Link pubblici** senza login",
            'editor_title' => 'Editor canvas',
            'editor_body' => "Sposta, ridimensiona, ruota — diapositive **1920×1080**.\n\nPannello proprietà, livelli, autosalvataggio.",
            'editor_caption' => 'Editor Konva.js con barre degli strumenti',
            'content_title' => 'Contenuti ricchi',
            'content_body' => "**Testo** Markdown, forme, immagini, video, audio.\n\nSfondi: colore, gradiente, immagine, video.",
            'anim_title' => 'Animazioni passo passo',
            'anim_body' => "Ogni oggetto può animarsi — clicca per vedere.",
            'tpl_title' => 'Modelli & brand',
            'tpl_body' => "- **Modelli di diapositive** e stili testo\n- **Colori brand** modificabili\n- **Pixabay** (opzionale)",
            'tpl_caption' => 'Modelli, stili testo e palette',
            'present_title' => 'Modalità presentazione',
            'present_body' => "Vero **reveal.js** — non screenshot.\n\nSync live, puntatore laser, note.",
            'present_caption' => 'Presentazione a schermo intero',
            'export_title' => 'Export',
            'export_body' => "- **HTML offline** · **PDF**\n- **PPTX / ODP** *(con limitazioni — layout e animazioni semplificati)*\n- Link pubblico",
            'oss_title' => 'Open source · MIT',
            'oss_body' => $repoLinkMd,
            'langs_title' => 'Altre lingue',
            'try_title' => 'Prova SlideForge',
            'try_body' => "**Demo** (reset 12h)\n{$demoLinkMd}\n\n**admin** / **admin**",
            'try_end' => 'Grazie per aver seguito il tour!',
        ],
        'rm' => [
            'nav_title' => 'Navigaziun',
            'nav_body' => "**Enavos:** Fritga a dretga u Space\n**En'ur:** Fritga a sanestra\n**Survista:** Esc · **Maletg entir:** F\n\nAnimaziuns: Space u → per mintga pass",
            'title' => 'SlideForge',
            'subtitle' => 'Editur self-hosted per presentaziuns reveal.js',
            'hosting_title' => 'Hosting simpel',
            'hosting_body' => "**Nagina banca da datas** — JSON sut `data/`.\n\n**PHP 8.2+** cun nginx, Apache u IIS — senza Composer.",
            'dash_title' => 'Dashboard',
            'dash_caption' => 'Preschentaziuns proprias e divididas',
            'share_title' => 'Multi-user & divider',
            'share_body' => "- Rollas: **Admin** & **Editor**\n- Divider cun lectura u editar\n- **Links publics** senza login",
            'editor_title' => 'Editur canvas',
            'editor_body' => "Spostar, redimensionar, rotar — slides **1920×1080**.\n\nPanel proprietads, auto-save.",
            'editor_caption' => 'Editur Konva.js',
            'content_title' => 'Cuntegn rich',
            'content_body' => "**Text** Markdown, furmas, maletgs, video, audio.",
            'anim_title' => 'Animaziuns pass per pass',
            'anim_body' => "Mintga object po s'animar — clicca per vesair.",
            'tpl_title' => 'Models & colurs',
            'tpl_body' => "- **Models da slides** e stils da text\n- **Colurs da marca**",
            'tpl_caption' => 'Models e palette',
            'present_title' => 'Modus presentaziun',
            'present_body' => "Ver **reveal.js** — betg mo screenshots.\n\nSync live, laser, notas.",
            'present_caption' => 'Presentaziun a plen maletg',
            'export_title' => 'Export',
            'export_body' => "- **HTML offline** · **PDF**\n- **PPTX / ODP** *(cun limitaziuns — layout e animaziuns simplifitgadas)*",
            'oss_title' => 'Open source · MIT',
            'oss_body' => $repoLinkMd,
            'langs_title' => 'Autras linguas',
            'try_title' => 'Emprovar SlideForge',
            'try_body' => "**Demo** (reset 12h)\n{$demoLinkMd}",
            'try_end' => 'Grazia per quest turn!',
        ],
    ];
}

function textObj(string $id, int $x, int $y, int $w, int $h, string $text, array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'type' => 'text',
        'rotation' => 0,
        'opacity' => 1,
        'animType' => 'none',
        'animOrder' => 1,
        'animAutoAdvance' => 0,
        'animDuration' => 0,
        'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
        'text' => $text,
        'fontFamily' => 'Open Sans',
        'fontSize' => 36,
        'fontWeight' => 'normal',
        'italic' => false,
        'underline' => false,
        'strikethrough' => false,
        'uppercase' => false,
        'smallCaps' => false,
        'animPerLine' => false,
        'color' => '#ffffff',
        'align' => 'left',
    ], $extra);
}

function heading(string $id, string $text, int $y = 60): array
{
    return textObj($id, 100, $y, 1720, 80, $text, [
        'fontSize' => 56, 'fontWeight' => 'bold', 'animType' => 'fade-in', 'animDuration' => 800,
    ]);
}

function bodyText(string $id, string $text, int $y = 180, bool $perLine = true): array
{
    return textObj($id, 100, $y, 900, 500, $text, [
        'fontSize' => 34,
        'animType' => 'fade-up',
        'animOrder' => 2,
        'animDuration' => 1000,
        'animPerLine' => $perLine,
    ]);
}

/** Sofort sichtbar — für Navigationsfolie ohne Fragment-Animation. */
function staticHeading(string $id, string $text, int $y = 60): array
{
    return textObj($id, 100, $y, 1720, 80, $text, [
        'fontSize' => 56, 'fontWeight' => 'bold',
    ]);
}

function staticBody(string $id, string $text, int $y = 180): array
{
    return textObj($id, 100, $y, 900, 500, $text, [
        'fontSize' => 34, 'animPerLine' => false,
    ]);
}

function screenshotSlide(string $prefix, string $title, string $caption, string $file, string $transition = 'fade'): array
{
    $src = "asset.php?id={$GLOBALS['seedAssetId']}&file={$file}";
    $objects = [
        heading("{$prefix}h", $title),
        textObj("{$prefix}c", 100, 920, 1720, 50, $caption, [
            'fontSize' => 24, 'color' => '#8b92a3', 'align' => 'center', 'animType' => 'fade-up', 'animOrder' => 3,
        ]),
    ];
    if (is_file($GLOBALS['assetsDir'] . '/' . $file)) {
        array_splice($objects, 1, 0, [[
            'id' => "{$prefix}img",
            'type' => 'image',
            'rotation' => 0,
            'opacity' => 1,
            'animType' => 'fade-in',
            'animOrder' => 2,
            'animAutoAdvance' => 0,
            'animDuration' => 1000,
            'x' => 160,
            'y' => 160,
            'w' => 1600,
            'h' => 720,
            'src' => $src,
            'stroke' => '#61a8e0',
            'strokeWidth' => 2,
        ]]);
    }
    return [
        'id' => $prefix,
        'background' => ['type' => 'color', 'value' => '#15181e'],
        'transition' => $transition,
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => $objects,
    ];
}

function buildSlides(string $lang, array $s): array
{
    $slides = [];

    $slides[] = [
        'id' => 'nav',
        'background' => ['type' => 'color', 'value' => '#0c0e12'],
        'transition' => 'fade',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [
            staticHeading('navh', $s['nav_title'], 100),
            textObj('navb', 100, 180, 820, 420, $s['nav_body'], [
                'fontSize' => 34, 'animPerLine' => false,
            ]),
            textObj('navlt', 960, 140, 860, 60, $s['langs_title'], [
                'fontSize' => 44, 'fontWeight' => 'bold', 'align' => 'center',
            ]),
            textObj('navll', 960, 230, 860, 120, langLinksMd(), [
                'fontSize' => 36, 'align' => 'center', 'color' => '#61a8e0',
            ]),
        ],
    ];

    $slides[] = [
        'id' => 'title',
        'background' => ['type' => 'color', 'value' => '#1a2234'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [
            textObj('t1', 120, 340, 1680, 120, $s['title'], [
                'fontSize' => 100, 'fontWeight' => 'bold', 'align' => 'center',
                'animType' => 'fade-in', 'animDuration' => 1000,
            ]),
            textObj('t2', 120, 480, 1680, 80, $s['subtitle'], [
                'fontSize' => 42, 'color' => '#61a8e0', 'align' => 'center',
                'animType' => 'fade-up', 'animOrder' => 2, 'animDuration' => 1000,
            ]),
        ],
    ];

    $slides[] = [
        'id' => 'hosting',
        'background' => ['type' => 'gradient', 'color1' => '#3a6c8d', 'color2' => '#15181e', 'angle' => 160,
            'value' => 'linear-gradient(160deg, #3a6c8d, #15181e)'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('h1', $s['hosting_title']), bodyText('h1b', $s['hosting_body'])],
    ];

    $slides[] = screenshotSlide('dash', $s['dash_title'], $s['dash_caption'], 'ui-dashboard.png');

    $slides[] = [
        'id' => 'share',
        'background' => ['type' => 'color', 'value' => '#0c0e12'],
        'transition' => 'convex',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('sh1', $s['share_title']), bodyText('sh1b', $s['share_body'])],
    ];

    $slides[] = screenshotSlide('ed', $s['editor_title'], $s['editor_caption'], 'ui-editor.png', 'slide');

    $slides[] = [
        'id' => 'content',
        'background' => ['type' => 'color', 'value' => '#111318'],
        'transition' => 'zoom',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('c1', $s['content_title']), bodyText('c1b', $s['content_body'])],
    ];

    $slides[] = [
        'id' => 'anim',
        'background' => ['type' => 'color', 'value' => '#1a2234'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => array_merge(
            [heading('a1', $s['anim_title']), bodyText('a1b', $s['anim_body'], 180, false)],
            array_map(fn($i) => [
                'id' => "ab{$i}",
                'type' => 'rect',
                'rotation' => 0,
                'opacity' => 1,
                'animType' => ['fade-in', 'fade-left', 'fade-right', 'grow'][$i - 1],
                'animOrder' => $i + 1,
                'animAutoAdvance' => 0,
                'animDuration' => 700,
                'x' => 900 + ($i - 1) * 200,
                'y' => 400,
                'w' => 160,
                'h' => 80,
                'fillType' => 'solid',
                'fill' => ['#3a6c8d', '#61a8e0', '#87b42b', '#d9c23a'][$i - 1],
                'stroke' => 'transparent',
                'strokeWidth' => 0,
            ], range(1, 4))
        ),
    ];

    $slides[] = screenshotSlide('tpl', $s['tpl_title'], $s['tpl_caption'], 'ui-templates.png', 'fade');

    $slides[] = [
        'id' => 'tpltxt',
        'background' => ['type' => 'color', 'value' => '#0c0e12'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('tp1', $s['tpl_title']), bodyText('tp1b', $s['tpl_body'])],
    ];

    $slides[] = screenshotSlide('pres', $s['present_title'], $s['present_caption'], 'ui-present.png', 'zoom');

    $slides[] = [
        'id' => 'prestxt',
        'background' => ['type' => 'color', 'value' => '#15181e'],
        'transition' => 'fade',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('pr1', $s['present_title']), bodyText('pr1b', $s['present_body'])],
    ];

    $slides[] = [
        'id' => 'export',
        'background' => ['type' => 'gradient', 'color1' => '#3a6c8d', 'color2' => '#87b42b', 'angle' => 135,
            'value' => 'linear-gradient(135deg, #3a6c8d, #87b42b)'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [heading('ex1', $s['export_title']), bodyText('ex1b', $s['export_body'])],
    ];

    $slides[] = [
        'id' => 'oss',
        'background' => ['type' => 'color', 'value' => '#1a2234'],
        'transition' => 'fade',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [
            heading('os1', $s['oss_title'], 300),
            textObj('os1b', 100, 440, 1720, 80, $s['oss_body'], [
                'fontSize' => 40, 'color' => '#87b42b', 'align' => 'center', 'animType' => 'fade-up', 'animOrder' => 2,
            ]),
        ],
    ];

    $slides[] = [
        'id' => 'try',
        'background' => ['type' => 'color', 'value' => '#0c0e12'],
        'transition' => 'slide',
        'autoAdvance' => 0,
        'notes' => '',
        'objects' => [
            textObj('try1', 120, 260, 1680, 100, $s['try_title'], [
                'fontSize' => 72, 'fontWeight' => 'bold', 'align' => 'center', 'animType' => 'fade-in',
            ]),
            array_merge(bodyText('try1b', $s['try_body'], 400, true), ['x' => 360, 'w' => 1200, 'align' => 'center']),
            textObj('try1c', 120, 720, 1680, 60, $s['try_end'], [
                'fontSize' => 30, 'fontWeight' => 'bold', 'color' => '#87b42b', 'align' => 'center',
                'animType' => 'grow', 'animOrder' => 3, 'animDuration' => 1200,
            ]),
        ],
    ];

    return $slides;
}

$strings = tourStrings();
$GLOBALS['seedAssetId'] = $seedAssetId;
$GLOBALS['assetsDir'] = $assetsDir;

// Alte Einzelstruktur entfernen
foreach (['meta.json', 'slides.json'] as $old) {
    $p = $seedRoot . '/' . $old;
    if (is_file($p)) {
        unlink($p);
    }
}

foreach ($languages as $code => $cfg) {
    if (!isset($strings[$code])) {
        fwrite(STDERR, "Keine Strings für $code\n");
        continue;
    }
    $dir = $seedRoot . '/' . $code;
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $meta = [
        'title' => $cfg['title'],
        'width' => 1920,
        'height' => 1080,
        'seed_asset_id' => $seedAssetId,
        'lang' => $code,
        'show_progress' => true,
        'show_controls' => true,
        'public_link' => ['enabled' => true, 'token' => $cfg['token']],
    ];
    file_put_contents(
        $dir . '/meta.json',
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    $slides = ['slides' => buildSlides($code, $strings[$code])];
    file_put_contents(
        $dir . '/slides.json',
        json_encode($slides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo "  $code → {$cfg['token']} (" . count($slides['slides']) . " slides)\n";
}

echo "Fertig. Assets in seed/feature-tour/assets/\n";
