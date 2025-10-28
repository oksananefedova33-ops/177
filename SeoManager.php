<?php
declare(strict_types=1);

/**
 * SEO Manager - универсальный модуль для управления SEO
 * Работает на любом домене без настройки
 */
class SeoManager {
    private string $baseUrl;
    private string $currentUrl;
    private array $config;
    private PDO $pdo;
    
    public function __construct(PDO $pdo, array $config = []) {
    $this->pdo = $pdo;
    $this->detectUrls();
    $this->config = array_merge($this->getDefaultConfig(), $config);

    // Для статического экспорта: подменяем baseUrl на токен, чтобы не «зашивать» домен
    if (!empty($this->config['base_url_token'])) {
        $token = rtrim((string)$this->config['base_url_token'], '/');
        $this->baseUrl    = $token;
        $this->currentUrl = $token;
    }

    $this->ensureSettings();
}

    
    /**
     * Автоматическое определение URLs
     */
    private function detectUrls(): void {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $this->baseUrl = "{$scheme}://{$host}";
        $this->currentUrl = "{$scheme}://{$host}{$uri}";
    }
    
    /**
     * Конфигурация по умолчанию
     */
    private function getDefaultConfig(): array {
        return [
            'site_name' => 'My Website',
            'site_description' => '',
            'default_image' => '',
            'twitter_handle' => '',
            'organization_name' => '',
            'organization_logo' => '',
            'favicon' => '/favicon.ico',
            'enable_json_ld' => true,
            'enable_og' => true,
            'enable_twitter' => true,
        ];
    }
    
    /**
     * Создание таблицы настроек если её нет
     */
    private function ensureSettings(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS seo_settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");
        
        // Загружаем сохраненные настройки
        $stmt = $this->pdo->query("SELECT key, value FROM seo_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->config[$row['key']] = $row['value'];
        }
    }
    
    /**
     * Сохранение настроек
     */
    public function saveSettings(array $settings): void {
        foreach ($settings as $key => $value) {
            $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO seo_settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            $this->config[$key] = $value;
        }
    }
    
    /**
     * Генерация всех SEO-тегов для страницы
     */
    public function generateMetaTags(array $page, string $lang = 'ru', array $languages = []): string {
        $html = [];
        
        // Базовые meta
        $html[] = $this->generateBasicMeta($page, $lang);
        
        // Canonical
        $html[] = $this->generateCanonical($page, $lang);
        
        // Hreflang
        if (!empty($languages)) {
            $html[] = $this->generateHreflang($page, $languages, $lang);
        }
        
        // Open Graph
        if ($this->config['enable_og']) {
            $html[] = $this->generateOpenGraph($page, $lang);
        }
        
        // Twitter Cards
        if ($this->config['enable_twitter']) {
            $html[] = $this->generateTwitterCard($page);
        }
        
        // Favicon и иконки
        $html[] = $this->generateIcons();
        
        return implode("\n", array_filter($html));
    }
    
    /**
     * Базовые meta-теги
     */
    private function generateBasicMeta(array $page, string $lang): string {
        $title = htmlspecialchars($page['meta_title'] ?? $page['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="language" content="{$lang}">
<meta name="robots" content="index, follow">
<title>{$title}</title>
<meta name="description" content="{$description}">
HTML;
    }
    
    /**
     * Canonical URL
     */
    private function generateCanonical(array $page, string $lang): string {
        $url = $this->getPageUrl($page, $lang);
        return "<link rel=\"canonical\" href=\"{$url}\">";
    }
    
    /**
     * Hreflang теги
     */
    private function generateHreflang(array $page, array $languages, string $currentLang): string {
        $html = [];
        
        foreach ($languages as $lang) {
            $url = $this->getPageUrl($page, $lang);
            $html[] = "<link rel=\"alternate\" hreflang=\"{$lang}\" href=\"{$url}\">";
        }
        
        // x-default на основной язык
        $defaultUrl = $this->getPageUrl($page, $languages[0] ?? 'ru');
        $html[] = "<link rel=\"alternate\" hreflang=\"x-default\" href=\"{$defaultUrl}\">";
        
        return implode("\n", $html);
    }
    
    /**
     * Open Graph теги
     */
    private function generateOpenGraph(array $page, string $lang): string {
        $title = htmlspecialchars($page['meta_title'] ?? $page['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($page['meta_description'] ?? $this->config['site_description'] ?? '', ENT_QUOTES, 'UTF-8');
        $url = $this->getPageUrl($page, $lang);
        $image = $this->getPageImage($page) ?? $this->config['default_image'] ?? '';
        $siteName = htmlspecialchars($this->config['site_name'], ENT_QUOTES, 'UTF-8');
        
        $ogLocale = $this->mapLangToOgLocale($lang);
        
        $html = <<<HTML
<meta property="og:type" content="website">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$description}">
<meta property="og:url" content="{$url}">
<meta property="og:site_name" content="{$siteName}">
<meta property="og:locale" content="{$ogLocale}">
HTML;
        
        if ($image) {
            $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
            $html .= "\n<meta property=\"og:image\" content=\"{$image}\">";
            $html .= "\n<meta property=\"og:image:width\" content=\"1200\">";
            $html .= "\n<meta property=\"og:image:height\" content=\"630\">";
        }
        
        return $html;
    }
    
    /**
     * Twitter Card теги
     */
    private function generateTwitterCard(array $page): string {
        $title = htmlspecialchars($page['meta_title'] ?? $page['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8');
        $image = $this->getPageImage($page) ?? $this->config['default_image'] ?? '';
        
        $html = <<<HTML
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$title}">
<meta name="twitter:description" content="{$description}">
HTML;
        
        if ($image) {
            $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
            $html .= "\n<meta name=\"twitter:image\" content=\"{$image}\">";
        }
        
        if (!empty($this->config['twitter_handle'])) {
            $handle = htmlspecialchars($this->config['twitter_handle'], ENT_QUOTES, 'UTF-8');
            $html .= "\n<meta name=\"twitter:site\" content=\"@{$handle}\">";
        }
        
        return $html;
    }
    
    /**
     * Favicon и иконки
     */
    private function generateIcons(): string {
        $favicon = $this->config['favicon'];
        
        return <<<HTML
<link rel="icon" type="image/x-icon" href="{$favicon}">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
HTML;
    }
    
    /**
     * JSON-LD структурированные данные
     */
    public function generateJsonLd(array $page, string $lang = 'ru'): string {
        if (!$this->config['enable_json_ld']) {
            return '';
        }
        
        $schemas = [];
        
        // WebSite schema
        $schemas[] = $this->generateWebSiteSchema($lang);
        
        // Organization schema
        if (!empty($this->config['organization_name'])) {
            $schemas[] = $this->generateOrganizationSchema();
        }
        
        // WebPage schema
        $schemas[] = $this->generateWebPageSchema($page, $lang);
        
        $json = json_encode($schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        return <<<HTML
<script type="application/ld+json">
{$json}
</script>
HTML;
    }
    
    /**
     * WebSite schema
     */
    private function generateWebSiteSchema(string $lang): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $this->baseUrl,
            'name' => $this->config['site_name'],
            'description' => $this->config['site_description'],
            'inLanguage' => $lang,
        ];
    }
    
    /**
     * Organization schema
     */
    private function generateOrganizationSchema(): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->config['organization_name'],
            'url' => $this->baseUrl,
        ];
        
        if (!empty($this->config['organization_logo'])) {
            $schema['logo'] = $this->config['organization_logo'];
        }
        
        return $schema;
    }
    
    /**
     * WebPage schema
     */
    private function generateWebPageSchema(array $page, string $lang): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => $this->getPageUrl($page, $lang),
            'name' => $page['meta_title'] ?? $page['name'] ?? '',
            'description' => $page['meta_description'] ?? '',
            'inLanguage' => $lang,
        ];
    }
    
    /**
     * Получение URL страницы
     */
    private function getPageUrl(array $page, string $lang): string {
        $isHome = ($page['is_home'] ?? false) || ($page['id'] ?? 0) === 1;
        
        if ($isHome) {
            return $lang === 'ru' ? $this->baseUrl . '/' : $this->baseUrl . '/?lang=' . $lang;
        }
        
        $slug = $page['slug'] ?? '';
        if ($slug) {
            $url = $this->baseUrl . '/' . $slug;
        } else {
            $url = $this->baseUrl . '/?id=' . ($page['id'] ?? 0);
        }
        
        if ($lang !== 'ru') {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'lang=' . $lang;
        }
        
        return $url;
    }
    
    /**
     * Извлечение первого изображения со страницы
     */
    private function getPageImage(array $page): ?string {
        $data = json_decode($page['data_json'] ?? '{}', true);
        $elements = $data['elements'] ?? [];
        
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'image' && !empty($el['src'])) {
                $src = $el['src'];
                
                // Если относительный путь, делаем абсолютным
                if (!preg_match('/^https?:\/\//', $src)) {
                    $src = $this->baseUrl . '/' . ltrim($src, '/');
                }
                
                return $src;
            }
        }
        
        return null;
    }
    
    /**
     * Маппинг языка в OG locale
     */
    private function mapLangToOgLocale(string $lang): string {
        $map = [
            'ru' => 'ru_RU',
            'en' => 'en_US',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'it' => 'it_IT',
            'pt' => 'pt_PT',
            'ja' => 'ja_JP',
            'ko' => 'ko_KR',
            'zh-Hans' => 'zh_CN',
        ];
        
        return $map[$lang] ?? 'en_US';
    }
    
    /**
     * Генерация robots.txt
     */
    public function generateRobotsTxt(array $pages, array $languages): string {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /editor/',
            'Disallow: /data/',
            '',
            'Sitemap: ' . $this->baseUrl . '/sitemap.xml',
        ];
        
        return implode("\n", $lines);
    }
    
    /**
     * Генерация sitemap.xml (многоязычная)
     */
    public function generateSitemap(array $pages, array $languages, string $primaryLang = 'ru'): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        foreach ($pages as $page) {
            foreach ($languages as $lang) {
                $loc = $this->getPageUrl($page, $lang);
                $priority = ($page['is_home'] ?? false) ? '1.0' : '0.8';
                if ($lang !== $primaryLang) {
                    $priority = ($page['is_home'] ?? false) ? '0.9' : '0.7';
                }
                
                $xml .= "  <url>\n";
                $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
                
                // Альтернативные языковые версии
                foreach ($languages as $altLang) {
                    $altLoc = $this->getPageUrl($page, $altLang);
                    $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$altLang}\" href=\"" . htmlspecialchars($altLoc, ENT_XML1) . "\"/>\n";
                }
                
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>{$priority}</priority>\n";
                $xml .= "  </url>\n";
            }
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Оптимизация HTML изображений
     */
    public function optimizeImages(string $html, string $defaultAlt = ''): string {
        // Добавляем loading="lazy" и decoding="async"
        $html = preg_replace_callback(
            '/<img([^>]*)>/i',
            function($matches) use ($defaultAlt) {
                $attrs = $matches[1];
                
                // Проверяем, есть ли уже эти атрибуты
                if (stripos($attrs, 'loading=') === false) {
                    $attrs .= ' loading="lazy"';
                }
                
                if (stripos($attrs, 'decoding=') === false) {
                    $attrs .= ' decoding="async"';
                }
                
                // Добавляем alt если его нет
                if (stripos($attrs, 'alt=') === false && $defaultAlt) {
                    $attrs .= ' alt="' . htmlspecialchars($defaultAlt, ENT_QUOTES) . '"';
                }
                
                return '<img' . $attrs . '>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Добавление rel="noopener noreferrer" к внешним ссылкам
     */
    public function secureLinks(string $html): string {
        $html = preg_replace_callback(
            '/<a([^>]*target=["\']_blank["\'][^>]*)>/i',
            function($matches) {
                $attrs = $matches[1];
                
                if (stripos($attrs, 'rel=') === false) {
                    $attrs .= ' rel="noopener noreferrer"';
                } else {
                    // Добавляем к существующему rel
                    $attrs = preg_replace(
                        '/rel=["\']([^"\']*)["\']/',
                        'rel="$1 noopener noreferrer"',
                        $attrs
                    );
                }
                
                return '<a' . $attrs . '>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Генерация build version (для кеширования)
     */
    public function getBuildVersion(): string {
        $cacheFile = dirname(__DIR__, 2) . '/data/.build_version';
        
        if (file_exists($cacheFile)) {
            return trim(file_get_contents($cacheFile));
        }
        
        $version = date('YmdHis');
        @file_put_contents($cacheFile, $version);
        
        return $version;
    }
}