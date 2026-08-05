<?php
/**
 * Plugin Name: ARKAN Official Landing Pages
 * Description: Serves the real-estate campaign landing pages on arkans.sa without replacing the existing website, while preserving the existing lead system.
 * Version: 1.0.0
 * Author: Horizons
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Arkan_Official_Landings {
    private const SOURCE_ORIGIN = 'https://arkan-realestate-solutions.hositee.com';
    private const OFFICIAL_ORIGIN = 'https://arkans.sa';
    private const CACHE_TTL = 300;

    /** @var string[] */
    private const PUBLIC_PATHS = [
        '/',
        '/حلول-التمويل-العقاري/',
        '/رفض-التمويل-العقاري/',
        '/تمويل-عقاري-مع-التزامات/',
        '/شراء-مديونية-عقارية/',
        '/شراء-عقار-بالتمويل/',
        '/تم-استلام-الطلب/',
    ];

    /** @var string[] */
    private const INDEXABLE_PATHS = [
        '/حلول-التمويل-العقاري/',
        '/رفض-التمويل-العقاري/',
        '/تمويل-عقاري-مع-التزامات/',
        '/شراء-مديونية-عقارية/',
        '/شراء-عقار-بالتمويل/',
    ];

    public static function boot(): void {
        add_action('init', [self::class, 'register_rewrites']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
        add_filter('robots_txt', [self::class, 'robots_txt'], 20, 2);
    }

    public static function activate(): void {
        self::register_rewrites();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }

    public static function register_rewrites(): void {
        foreach (self::PUBLIC_PATHS as $path) {
            if ($path === '/') {
                continue;
            }
            $slug = trim($path, '/');
            add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?arkan_landing=1', 'top');
        }

        add_rewrite_rule('^assets/(.+)$', 'index.php?arkan_asset=$matches[1]', 'top');
        add_rewrite_rule('^api/lead/?$', 'index.php?arkan_api=lead', 'top');
        add_rewrite_rule('^arkan-landings-sitemap\.xml$', 'index.php?arkan_sitemap=1', 'top');
    }

    /** @param string[] $vars @return string[] */
    public static function query_vars(array $vars): array {
        $vars[] = 'arkan_landing';
        $vars[] = 'arkan_asset';
        $vars[] = 'arkan_api';
        $vars[] = 'arkan_sitemap';
        return $vars;
    }

    public static function route(): void {
        if ((string) get_query_var('arkan_api') === 'lead') {
            self::proxy_lead_api();
        }

        $asset = (string) get_query_var('arkan_asset');
        if ($asset !== '') {
            self::proxy_asset($asset);
        }

        if ((string) get_query_var('arkan_sitemap') === '1') {
            self::render_sitemap();
        }

        if ((string) get_query_var('arkan_landing') === '1') {
            self::render_landing();
        }
    }

    private static function request_path(): string {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        $decoded = rawurldecode($path ?: '/');
        return '/' . trim($decoded, '/') . '/';
    }

    private static function render_landing(): void {
        $path = self::request_path();
        if (!in_array($path, self::PUBLIC_PATHS, true) || $path === '/') {
            return;
        }

        $source_url = self::SOURCE_ORIGIN . self::encode_path($path);
        $cache_key = 'arkan_lp_' . md5($source_url);
        $html = get_transient($cache_key);

        if (!is_string($html) || $html === '') {
            $response = wp_remote_get($source_url, [
                'timeout' => 20,
                'redirection' => 0,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                    'X-Arkan-Proxy' => '1',
                    'User-Agent' => 'ARKAN-Official-Landing-Proxy/1.0',
                ],
            ]);

            if (is_wp_error($response)) {
                self::service_unavailable();
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $html = (string) wp_remote_retrieve_body($response);
            if ($code !== 200 || $html === '') {
                self::service_unavailable();
            }

            $html = self::rewrite_html($html, $path);
            set_transient($cache_key, $html, self::CACHE_TTL);
        }

        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if ($path === '/تم-استلام-الطلب/') {
            header('X-Robots-Tag: noindex, nofollow');
        } else {
            header('X-Robots-Tag: index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
        }
        echo $html;
        exit;
    }

    private static function rewrite_html(string $html, string $path): string {
        $official_url = self::OFFICIAL_ORIGIN . self::encode_path($path);
        $html = str_replace(self::SOURCE_ORIGIN, self::OFFICIAL_ORIGIN, $html);
        $html = str_replace('href="/سياسة-الخصوصية/"', 'href="/privacy-policy/"', $html);
        $html = str_replace("href='/سياسة-الخصوصية/'", "href='/privacy-policy/'", $html);

        $canonical = '<link rel="canonical" href="' . esc_url($official_url) . '">';
        $html = preg_replace('#<link\s+rel=["\']canonical["\'][^>]*>#i', $canonical, $html, 1) ?: $html;
        $html = preg_replace('#<meta\s+property=["\']og:url["\'][^>]*>#i', '<meta property="og:url" content="' . esc_url($official_url) . '">', $html, 1) ?: $html;

        return $html;
    }

    private static function proxy_asset(string $asset): void {
        $asset = ltrim(rawurldecode($asset), '/');
        if ($asset === '' || str_contains($asset, '..') || !preg_match('#^[A-Za-z0-9._/-]+$#', $asset)) {
            status_header(404);
            exit;
        }

        $url = self::SOURCE_ORIGIN . '/assets/' . str_replace('%2F', '/', rawurlencode($asset));
        $cache_key = 'arkan_asset_' . md5($url);
        $cached = get_transient($cache_key);

        if (is_array($cached) && isset($cached['body'], $cached['type'])) {
            $body = (string) $cached['body'];
            $type = (string) $cached['type'];
        } else {
            $response = wp_remote_get($url, [
                'timeout' => 20,
                'headers' => ['X-Arkan-Proxy' => '1'],
            ]);
            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                status_header(404);
                exit;
            }
            $body = (string) wp_remote_retrieve_body($response);
            $type = (string) wp_remote_retrieve_header($response, 'content-type');
            if ($type === '') {
                $type = 'application/octet-stream';
            }
            set_transient($cache_key, ['body' => $body, 'type' => $type], DAY_IN_SECONDS);
        }

        status_header(200);
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=2592000, immutable');
        echo $body;
        exit;
    }

    private static function proxy_lead_api(): void {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            status_header(405);
            header('Allow: POST');
            exit;
        }

        $body = (string) file_get_contents('php://input');
        if ($body === '' || strlen($body) > 65536) {
            status_header(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo wp_json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }

        $response = wp_remote_post(self::SOURCE_ORIGIN . '/api/lead', [
            'timeout' => 25,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Arkan-Proxy' => '1',
                'X-Forwarded-Host' => 'arkans.sa',
            ],
            'body' => $body,
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            status_header(502);
            header('Content-Type: application/json; charset=UTF-8');
            echo wp_json_encode(['ok' => false, 'error' => 'upstream_unavailable']);
            exit;
        }

        status_header((int) wp_remote_retrieve_response_code($response));
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo (string) wp_remote_retrieve_body($response);
        exit;
    }

    private static function render_sitemap(): void {
        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (self::INDEXABLE_PATHS as $path) {
            echo '<url><loc>' . esc_url(self::OFFICIAL_ORIGIN . self::encode_path($path)) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>';
        }
        echo '</urlset>';
        exit;
    }

    public static function robots_txt(string $output, bool $public): string {
        if (!$public) {
            return $output;
        }
        $line = 'Sitemap: ' . self::OFFICIAL_ORIGIN . '/arkan-landings-sitemap.xml';
        if (!str_contains($output, $line)) {
            $output = rtrim($output) . "\n" . $line . "\n";
        }
        return $output;
    }

    private static function encode_path(string $path): string {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn($part) => $part !== ''));
        return '/' . implode('/', array_map('rawurlencode', $parts)) . '/';
    }

    private static function service_unavailable(): void {
        status_header(503);
        header('Retry-After: 120');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الخدمة قيد التحديث</title><body><main style="font-family:Arial,sans-serif;max-width:680px;margin:12vh auto;padding:24px;text-align:center"><h1>الخدمة قيد التحديث</h1><p>جرّب مرة أخرى بعد قليل.</p></main></body></html>';
        exit;
    }
}

Arkan_Official_Landings::boot();
register_activation_hook(__FILE__, [Arkan_Official_Landings::class, 'activate']);
register_deactivation_hook(__FILE__, [Arkan_Official_Landings::class, 'deactivate']);
