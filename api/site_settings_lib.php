<?php
/**
 * Site-wide branding/content settings stored as a flat key-value table, so
 * the admin "Site Settings" panel and the public site (logo, hero text,
 * contact links, footer) both read from one source of truth instead of
 * hardcoded HTML. Unknown keys are ignored on write and missing keys fall
 * back to UPLOADGRAM_SITE_SETTING_DEFAULTS on read, so the table never needs
 * to be pre-seeded.
 */

require_once __DIR__ . '/db.php';

const UPLOADGRAM_SITE_SETTING_DEFAULTS = [
    'site_name' => 'آپلود گرام',
    'tagline' => 'رشد واقعی برای کانال‌ها و صفحات شما',
    'logo_url' => '',
    'hero_title' => '',
    'hero_subtitle' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'contact_telegram' => '',
    'social_instagram' => '',
    'social_youtube' => '',
    'social_twitter' => '',
    'footer_text' => '',
];

function uploadgram_get_site_settings(): array {
    $db = uploadgram_db();
    $rows = $db->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
    $settings = UPLOADGRAM_SITE_SETTING_DEFAULTS;
    foreach ($rows as $row) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $settings;
}

function uploadgram_set_site_settings(array $values): array {
    $db = uploadgram_db();
    $stmt = $db->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($values as $key => $value) {
        if (!array_key_exists($key, UPLOADGRAM_SITE_SETTING_DEFAULTS)) {
            continue;
        }
        $stmt->execute([$key, (string) $value]);
    }
    return uploadgram_get_site_settings();
}
