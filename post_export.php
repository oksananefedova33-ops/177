<?php
/**
 * Export Finalizer — Variant C (железобетонный)
 * Поддерживает: валидацию домена, canonical/hreflang инъекцию в HTML,
 * статический sitemap.xml с xhtml:link, валидный robots.txt (абсолютный Sitemap),
 * генерацию .htaccess / nginx.conf для редиректов, diagnostics.txt,
 * упаковку в ZIP и CLI-запуск.
 *
 * PHP 7.2+ (ZipArchive требуется).
 */

namespace Export\Finalizer;

if (!class_exists(__NAMESPACE__ . '\PostExport')) {

    class PostExport
    {
        /** Точка входа (и для веб, и для CLI) */
        public static function entry(array $opts = []): void
        {
            $self = new self();
            $self->run($opts);
        }

        /** Основная логика */
        public function run(array $opts): void
        {
            $opt = Options::fromArray($opts);

            if (!is_dir($opt->exportDir)) {
                $this->fail("Export dir not found: {$opt->exportDir}");
            }

            // 1) Сканируем HTML, формируем карту страниц/языков
            $scan = ExportScanner::scanHtml($opt->exportDir, $opt->primaryLang);
            if (empty($scan['pages'])) {
                $this->fail("No HTML pages were found in export dir: {$opt->exportDir}");
            }
            $pagesBySlug = $scan['pages']; // [slug => [ lang => ['path'=>rel, 'is_home'=>bool] ]]
            $langs       = $scan['langs']; // ['ru','en',...]

            // 2) Построитель URL-ов
            $url = new UrlBuilder($opt);

            // 3) Инъекция SEO-тегов в каждый HTML
            $inj = new HtmlHeadInjector($opt, $url, $langs, $pagesBySlug);
            $inj->processAll();

            // 4) Генерация sitemap.xml и robots.txt
            (new SitemapBuilder($opt, $url, $langs, $pagesBySlug))->make();
            (new RobotsBuilder($opt, $url))->make();

            // 5) Конфиги редиректов
            (new ConfBuilder($opt, $url))->make();

            // 6) Диагностика
            (new Diagnostics($opt, $langs, $pagesBySlug))->write();

            // 7) Упаковка в ZIP + отдача/вывод результата
            $zipPath = Zipper::pack($opt);
            Out::deliver($zipPath, $opt);
        }

        private function fail(string $msg): void
        {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "[Export Finalizer] ERROR: {$msg}\n");
                exit(2);
            }
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "[Export Finalizer] ERROR: {$msg}";
            exit;
        }
    }

    /** ================== Модель опций ================== */
    final class Options
    {
        public $exportDir;
        public $domain;        // https://example.com
        public $host;          // example.com
        public $https;         // 1/0
        public $wwwMode;       // keep|www|non-www
        public $forceHost;     // 1/0 — принуд. редирект на домен
        public $primaryLang;   // ru
        public $zipName;       // имя архива

        public static function fromArray(array $a): self
        {
            $self = new self();

            // export_dir (обяз.)
            $self->exportDir = rtrim((string)($a['export_dir'] ?? ''), '/');
            if ($self->exportDir === '') {
                self::failStatic('Option export_dir is required');
            }

            // схема/домен/редиректы
            $domainRaw       = trim((string)($a['domain'] ?? ''));
            $httpsFlag       = (int)($a['https'] ?? 1);
            $wwwMode         = (string)($a['www_mode'] ?? 'keep');     // keep|www|non-www
            $forceHost       = (int)($a['force_host'] ?? 0);

            // язык по умолчанию
            $primaryLang     = trim((string)($a['primary_lang'] ?? 'ru'));
            if ($primaryLang === '') $primaryLang = 'ru';

            // zip name
            $zipName         = (string)($a['zip_name'] ?? ('site-' . date('Ymd-His') . '.zip'));

            // Нормализуем домен
            [$domain, $host] = self::normalizeDomain($domainRaw, $httpsFlag, $wwwMode);

            $self->domain      = $domain;
            $self->host        = $host;
            $self->https       = $httpsFlag ? 1 : 0;
            $self->wwwMode     = in_array($wwwMode, ['keep','www','non-www'], true) ? $wwwMode : 'keep';
            $self->forceHost   = $forceHost ? 1 : 0;
            $self->primaryLang = $primaryLang;
            $self->zipName     = $zipName;

            return $self;
        }

        /** Привести домен к виду https://example.com + host (IDN→ASCII, www‑режим) */
        private static function normalizeDomain(string $raw, int $https, string $wwwMode): array
        {
            $raw = trim($raw);
            if ($raw === '') {
                // Разрешаем экспорт без домена. Тогда canonical/во всех местах поставим {{BASE_URL}}
                return ['{{BASE_URL}}', ''];
            }
            // Добавим схему, если не указана
            if (!preg_match('~^https?://~i', $raw)) {
                $raw = ($https ? 'https://' : 'http://') . $raw;
            }
            $p = parse_url($raw);
            $host = strtolower($p['host'] ?? '');
            if ($host === '') self::failStatic('Invalid domain provided');

            // IDN → ASCII если доступно
            if (function_exists('idn_to_ascii')) {
                $idn = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
                if ($idn) $host = $idn;
            }

            // www режим
            if ($wwwMode === 'www' && strpos($host, 'www.') !== 0) {
                $host = 'www.' . $host;
            } elseif ($wwwMode === 'non-www' && strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }

            $scheme = $https ? 'https' : (isset($p['scheme']) ? strtolower($p['scheme']) : 'https');
            $domain = $scheme . '://' . $host;

            return [$domain, $host];
        }

        private static function failStatic(string $msg): void
        {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "[Export Finalizer] ERROR: {$msg}\n");
                exit(2);
            }
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo "[Export Finalizer] ERROR: {$msg}";
            exit;
        }
    }

    /** ================== Сканер HTML ================== */
    final class ExportScanner
    {
        /** Возвращает ['pages'=>[slug=>[lang=>['path','is_home']]], 'langs'=>[]] */
        public static function scanHtml(string $root, string $primaryLang): array
        {
            $pages = [];
            $langs = [$primaryLang => true];

            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($rii as $file) {
                if ($file->isDir()) continue;
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if ($ext !== 'html') continue;

                $rel = self::relPath($root, $file->getPathname());
                $base = basename($rel, '.html');

                $isHome = false;
                $lang = $primaryLang;
                $slug = $base;

                if ($base === 'index') {
                    $isHome = true;
                    $slug = 'index';
                    $lang = $primaryLang;
                } elseif (preg_match('~^index-([A-Za-z\-]+)$~', $base, $m)) {
                    $isHome = true;
                    $slug   = 'index';
                    $lang   = $m[1];
                } elseif (preg_match('~^(.+)-([A-Za-z\-]+)$~', $base, $m)) {
                    $slug = $m[1];
                    $lang = $m[2];
                } else {
                    $slug = $base;
                    $lang = $primaryLang;
                }

                $langs[$lang] = true;
                $pages[$slug][$lang] = ['path' => '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel), 'is_home' => $isHome];
            }

            return ['pages' => $pages, 'langs' => array_keys($langs)];
        }

        private static function relPath(string $root, string $abs): string
        {
            $root = rtrim(str_replace('\\','/',$root), '/') . '/';
            $abs  = str_replace('\\','/',$abs);
            return ltrim(substr($abs, strlen($root)), '/');
        }
    }

    /** ================== Построитель URL ================== */
    final class UrlBuilder
    {
        private $opt;
        public function __construct(Options $opt) { $this->opt = $opt; }

        public function base(): string { return rtrim($this->opt->domain, '/'); }

        /** Абсолютный URL для конкретной страницы */
        public function abs(string $path): string
        {
            if ($this->opt->domain === '{{BASE_URL}}') {
                return '{{BASE_URL}}' . $path;
            }
            return $this->base() . $path;
        }

        /** Путь до файла по slug/lang (совпадает с именованием файлов в экспорте) */
        public function pathFor(string $slug, string $lang, bool $isHome): string
        {
            $pl = $this->opt->primaryLang;
            if ($slug === 'index' && $isHome) {
                return ($lang === $pl) ? '/' : '/index-' . $lang . '.html';
            }
            if ($lang === $pl) return '/' . $slug . '.html';
            return '/' . $slug . '-' . $lang . '.html';
        }
    }

    /** ================== Инъектор SEO-тегов ================== */
    final class HtmlHeadInjector
    {
        private $opt, $url, $langs, $pages;
        public function __construct(Options $o, UrlBuilder $u, array $langs, array $pages)
        { $this->opt=$o; $this->url=$u; $this->langs=$langs; $this->pages=$pages; }

        public function processAll(): void
        {
            foreach ($this->pages as $slug => $byLang) {
                foreach ($byLang as $lang => $meta) {
                    $this->processFile($slug, $lang, $meta['path'], $meta['is_home']);
                }
            }
        }

        private function processFile(string $slug, string $lang, string $relPath, bool $isHome): void
        {
            $abs = rtrim($this->opt->exportDir, '/') . $relPath;
            if (!is_file($abs)) return;

            $html = @file_get_contents($abs);
            if ($html === false) return;

            // Счистим старые canonical/alternate/og:url/twitter:url
            $html = preg_replace('~<link[^>]+rel=["\']canonical["\'][^>]*>\s*~i', '', $html);
            $html = preg_replace('~<link[^>]+rel=["\']alternate["\'][^>]*hreflang=.+?>\s*~i', '', $html);
            $html = preg_replace('~<meta[^>]+property=["\']og:url["\'][^>]*>\s*~i', '', $html);
            $html = preg_replace('~<meta[^>]+name=["\']twitter:url["\'][^>]*>\s*~i', '', $html);

            // Сформируем canonical
            $canonicalAbs = $this->url->abs($this->url->pathFor($slug, $lang, $isHome));

            // hreflang
            // hreflang (self-referencing + reciprocal via common list)
            $links = [];

            // Только те локали, где эта страница реально существует
            $langList = array_keys($this->pages[$slug]);

            // Полный набор альтернатив (включая текущую локаль) — взаимность формируется автоматически
            foreach ($langList as $l) {
                $p = $this->url->pathFor($slug, $l, $this->pages[$slug][$l]['is_home']);
                $links[] = '<link rel="alternate" hreflang="' . htmlspecialchars($l, ENT_QUOTES) . '" href="' . htmlspecialchars($this->url->abs($p), ENT_QUOTES) . '">';
            }

            // Гарантируем самоссылку на текущую локаль, если по какой-то причине её нет в $langList
            if (!in_array($lang, $langList, true)) {
                $selfPath = $this->url->pathFor($slug, $lang, $isHome);
                $links[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang, ENT_QUOTES) . '" href="' . htmlspecialchars($this->url->abs($selfPath), ENT_QUOTES) . '">';
            }

            // x-default → первичный язык (или ru)
            $defaultLang = $this->opt->primaryLang ?: 'ru';
            $defaultPath = $this->url->pathFor($slug, $defaultLang, $this->pages[$slug][$defaultLang]['is_home'] ?? $isHome);
            $links[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($this->url->abs($defaultPath), ENT_QUOTES) . '">';

            // OG/Twitter url
            $og = [
                '<link rel="canonical" href="' . htmlspecialchars($canonicalAbs, ENT_QUOTES) . '">',
                '<meta property="og:url" content="' . htmlspecialchars($canonicalAbs, ENT_QUOTES) . '">',
                '<meta name="twitter:url" content="' . htmlspecialchars($canonicalAbs, ENT_QUOTES) . '">'
            ];
            $block = "\n<!-- SEO (export-generated) -->\n" . implode("\n", array_merge($og, $links)) . "\n<!-- /SEO -->\n";

            // Куда вставлять: перед </head>
            if (stripos($html, '</head>') !== false) {
                $html = preg_replace('~</head>~i', $block . '</head>', $html, 1);
            } else {
                $html .= $block;
            }

            // Исправляем относительные пути для изображений в OG и Twitter
            $baseUrl = $this->url->base();
            if ($baseUrl !== '{{BASE_URL}}') {
                // og:image
                $html = preg_replace_callback(
                    '/<meta\s+property="og:image"\s+content="([^"]+)"/i',
                    function($m) use ($baseUrl) {
                        $img = $m[1];
                        if (!preg_match('/^https?:\/\//i', $img) && !preg_match('/^\{\{BASE_URL\}\}/', $img)) {
                            $img = rtrim($baseUrl, '/') . '/' . ltrim($img, '/');
                        }
                        return '<meta property="og:image" content="' . $img . '"';
                    },
                    $html
                );
                
                // twitter:image
                $html = preg_replace_callback(
                    '/<meta\s+name="twitter:image"\s+content="([^"]+)"/i',
                    function($m) use ($baseUrl) {
                        $img = $m[1];
                        if (!preg_match('/^https?:\/\//i', $img) && !preg_match('/^\{\{BASE_URL\}\}/', $img)) {
                            $img = rtrim($baseUrl, '/') . '/' . ltrim($img, '/');
                        }
                        return '<meta name="twitter:image" content="' . $img . '"';
                    },
                    $html
                );
            }

            @file_put_contents($abs, $html);
        }
    }

    /** ================== Генератор sitemap.xml ================== */
    final class SitemapBuilder
    {
        private $opt, $url, $langs, $pages;
        public function __construct(Options $o, UrlBuilder $u, array $langs, array $pages)
        { $this->opt=$o; $this->url=$u; $this->langs=$langs; $this->pages=$pages; }

        public function make(): void
        {
            $lastmod = gmdate('Y-m-d\TH:i:s\Z');

            $xml = [];
            $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
            $xml[] = '        xmlns:xhtml="http://www.w3.org/1999/xhtml">';
            foreach ($this->pages as $slug => $byLang) {
                foreach ($byLang as $lang => $meta) {
                    $loc = $this->url->abs($this->url->pathFor($slug, $lang, $meta['is_home']));
                    $xml[] = '  <url>';
                    $xml[] = '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
                    $xml[] = '    <lastmod>' . $lastmod . '</lastmod>';
                    $xml[] = '    <priority>' . ($meta['is_home'] ? '1.0' : '0.8') . '</priority>';

                    // Альтернативы формируем только из реально существующих локалей страницы
                    $langList = array_keys($this->pages[$slug]);
                    foreach ($langList as $l) {
                        $p = $this->url->pathFor($slug, $l, $this->pages[$slug][$l]['is_home']);
                        $xml[] = '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($l, ENT_XML1) . '" href="' . htmlspecialchars($this->url->abs($p), ENT_XML1) . '"/>';
                    }

                    // Гарантируем самоссылку для текущей локали $lang
                    if (!in_array($lang, $langList, true)) {
                        $selfPath = $this->url->pathFor($slug, $lang, $meta['is_home']);
                        $xml[] = '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang, ENT_XML1) . '" href="' . htmlspecialchars($this->url->abs($selfPath), ENT_XML1) . '"/>';
                    }

                    // x-default → первичный язык (или ru)
                    $defLang = $this->opt->primaryLang ?: 'en';
                    $defPath = $this->url->pathFor($slug, $defLang, $this->pages[$slug][$defLang]['is_home'] ?? $meta['is_home']);
                    $xml[] = '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($this->url->abs($defPath), ENT_XML1) . '"/>';
                    $xml[] = '  </url>';
                }
            }
            $xml[] = '</urlset>';
            file_put_contents($this->opt->exportDir . '/sitemap.xml', implode("\n", $xml));
        }
    }

    /** ================== Генератор robots.txt ================== */
    final class RobotsBuilder
    {
        private $opt, $url;
        public function __construct(Options $o, UrlBuilder $u) { $this->opt=$o; $this->url=$u; }

        public function make(): void
        {
            $txt = "User-agent: *\nAllow: /\n\nDisallow: /editor/\nDisallow: /data/\n\n";
            $txt .= 'Sitemap: ' . $this->url->abs('/sitemap.xml') . "\n";
            file_put_contents($this->opt->exportDir . '/robots.txt', $txt);
        }
    }

    /** ================== Конфиги редиректов ================== */
    final class ConfBuilder
    {
        private $opt, $url;
        public function __construct(Options $o, UrlBuilder $u) { $this->opt=$o; $this->url=$u; }

        public function make(): void
        {
            $this->htaccess();
            $this->nginx();
        }

        private function htaccess(): void
        {
            $lines = [];
            $lines[] = 'RewriteEngine On';
            // HSTS (только при HTTPS)
if ($this->opt->https) {
    $lines[] = '<IfModule mod_headers.c>';
    $lines[] = 'Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"';
    $lines[] = '</IfModule>';
}

            // HTTPS
            if ($this->opt->https) {
                $lines[] = 'RewriteCond %{HTTPS} !=on';
                $lines[] = 'RewriteRule ^ https://' . ($this->opt->forceHost ? $this->opt->host : '%{HTTP_HOST}') . '%{REQUEST_URI} [L,R=301]';
            }
            // HOST
            if ($this->opt->forceHost && $this->opt->host) {
                $host = preg_quote($this->opt->host, '~');
                $lines[] = 'RewriteCond %{HTTP_HOST} !^' . $host . '$ [NC]';
                $lines[] = 'RewriteRule ^ ' . ($this->opt->https ? 'https' : 'http') . '://' . $this->opt->host . '%{REQUEST_URI} [L,R=301]';
            }
            $content = "# Canonical redirects\n" . implode("\n", $lines) . "\n";
            file_put_contents($this->opt->exportDir . '/.htaccess', $content);
        }

        private function nginx(): void
        {
            $host = $this->opt->host ?: 'example.com';
            $https = $this->opt->https ? 'https' : 'http';
            $conf = [];
            if ($this->opt->forceHost) {
                $conf[] = "server {";
                $conf[] = "    listen 80;";
                $conf[] = "    server_name _;";
                $conf[] = "    return 301 " . $https . "://{$host}\$request_uri;";
                $conf[] = "}";
            }
            $conf[] = "server {";
            $conf[] = "    listen " . ($this->opt->https ? '443 ssl' : '80') . ";";
            $conf[] = "    server_name {$host};";
            $conf[] = "    root /var/www/{$host}/public; # ← замените на ваш путь к экспорту";
            $conf[] = "    index index.html;";
            // HSTS (только при HTTPS)
if ($this->opt->https) {
    $conf[] = '    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;';
}

            if ($this->opt->https) {
                $conf[] = "    # ssl_certificate /etc/letsencrypt/live/{$host}/fullchain.pem;";
                $conf[] = "    # ssl_certificate_key /etc/letsencrypt/live/{$host}/privkey.pem;";
            }
            $conf[] = "    location / {";
            $conf[] = "        try_files \$uri \$uri/ =404;";
            $conf[] = "    }";
            $conf[] = "}";
            file_put_contents($this->opt->exportDir . '/nginx.conf', implode("\n", $conf) . "\n");
        }
    }

    /** ================== Диагностика ================== */
    final class Diagnostics
    {
        private $opt, $langs, $pages;
        public function __construct(Options $o, array $langs, array $pages) { $this->opt=$o; $this->langs=$langs; $this->pages=$pages; }

        public function write(): void
        {
            $lines = [];
            $lines[] = "Export diagnostics (generated " . gmdate('c') . " UTC)";
            $lines[] = "Domain: " . ($this->opt->domain ?: '(none)');
            $lines[] = "Primary language: " . $this->opt->primaryLang;
            $lines[] = "Languages detected: " . implode(', ', $this->langs);
            $countPages = 0;
            foreach ($this->pages as $slug => $byLang) {
                $langs = implode(', ', array_keys($byLang));
                $lines[] = " - {$slug}: {$langs}";
                $countPages += count($byLang);
            }
            $lines[] = "Total HTML files: {$countPages}";
            $lines[] = "";
            $lines[] = "robots.txt: should contain absolute Sitemap → " . ($this->opt->domain ? 'OK' : 'WARNING ({{BASE_URL}})');
            $lines[] = "sitemap.xml: static XML with xhtml:link → OK";
            $lines[] = ".htaccess/nginx.conf: generated → check and deploy manually";
            file_put_contents($this->opt->exportDir . '/diagnostics.txt', implode("\n", $lines) . "\n");
        }
    }

    /** ================== Упаковщик ZIP ================== */
    final class Zipper
    {
        public static function pack(Options $opt): string
        {
            $zipFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $opt->zipName;
            if (file_exists($zipFile)) @unlink($zipFile);

            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
                throw new \RuntimeException("Cannot create zip: {$zipFile}");
            }
            $root = rtrim($opt->exportDir, '/');
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if ($file->isDir()) continue;
                $abs = $file->getPathname();
                $rel = ltrim(str_replace($root, '', $abs), DIRECTORY_SEPARATOR);
                if ($rel === '') continue;
                $zip->addFile($abs, str_replace('\\','/',$rel));
            }
            $zip->close();
            return $zipFile;
        }
    }

    /** ================== Вывод результата ================== */
    final class Out
    {
        public static function deliver(string $zipPath, Options $opt): void
        {
            if (PHP_SAPI === 'cli') {
                echo $zipPath . PHP_EOL;
                return;
            }
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($zipPath));
            header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        }
    }
}
