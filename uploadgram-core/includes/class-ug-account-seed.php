<?php
/**
 * Curated premium-account catalogue used to seed WooCommerce products for the
 * "اکانت پرمیوم" section (no external API). Prices are in Toman and are
 * starting points — edit each product freely after seeding.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Account_Seed {

    /**
     * @return array<int,array{slug:string,name:string,desc:string,price:int,platform:string}>
     */
    public static function items(): array {
        return [
            // ── AI ──
            [ 'slug' => 'chatgpt-plus', 'name' => '🤖 ChatGPT Plus — اشتراک یک‌ماهه', 'price' => 165000, 'platform' => 'openai',
              'desc' => 'اشتراک یک‌ماههٔ ChatGPT Plus با دسترسی به جدیدترین مدل‌ها، سرعت بالا و اولویت در ساعات شلوغ. تحویل سریع و پشتیبانی کامل.' ],
            [ 'slug' => 'claude-pro', 'name' => '🧠 Claude Pro — اشتراک ماهانه', 'price' => 180000, 'platform' => 'anthropic',
              'desc' => 'اشتراک ماهانهٔ Claude Pro با محدودیت استفادهٔ بالاتر و دسترسی به مدل‌های پیشرفتهٔ Claude برای کارهای حرفه‌ای.' ],
            [ 'slug' => 'gemini-advanced', 'name' => '✨ Gemini Advanced — یک‌ماهه', 'price' => 155000, 'platform' => 'google',
              'desc' => 'دسترسی به Gemini Advanced گوگل با قوی‌ترین مدل‌ها و امکانات ویژهٔ هوش مصنوعی.' ],
            [ 'slug' => 'perplexity-pro', 'name' => '🔎 Perplexity Pro — اشتراک سالانه', 'price' => 140000, 'platform' => 'perplexity',
              'desc' => 'موتور جستجوی هوش مصنوعی Perplexity Pro با پاسخ‌های دقیق و منبع‌دار؛ اشتراک سالانه.' ],
            [ 'slug' => 'grok-super', 'name' => '⚡ Grok — اشتراک ویژه (X Premium)', 'price' => 160000, 'platform' => 'twitter',
              'desc' => 'دسترسی به Grok به‌همراه امکانات X Premium؛ مناسب کاربران حرفه‌ای شبکهٔ ایکس.' ],
            [ 'slug' => 'midjourney', 'name' => '🎨 Midjourney — پلن استاندارد', 'price' => 210000, 'platform' => 'midjourney',
              'desc' => 'ساخت تصاویر هنری با کیفیت بالا؛ پلن استاندارد Midjourney با ساعت GPU اختصاصی.' ],
            [ 'slug' => 'copilot-pro', 'name' => '💻 Copilot Pro — یک‌ماهه', 'price' => 190000, 'platform' => 'microsoft',
              'desc' => 'دستیار برنامه‌نویسی و بهره‌وری Microsoft Copilot Pro؛ اشتراک یک‌ماهه.' ],

            // ── Music ──
            [ 'slug' => 'spotify-premium', 'name' => '🎧 Spotify Premium — یک‌ماهه', 'price' => 45000, 'platform' => 'spotify',
              'desc' => 'اسپاتیفای پرمیوم بدون تبلیغ، با پخش آفلاین و کیفیت بالا. تحویل روی اکانت خودتان یا اکانت آماده.' ],
            [ 'slug' => 'youtube-music', 'name' => '🎵 YouTube Music — یک‌ماهه', 'price' => 47000, 'platform' => 'youtube',
              'desc' => 'یوتیوب موزیک پرمیوم؛ پخش بدون تبلیغ و دانلود آهنگ.' ],
            [ 'slug' => 'apple-music', 'name' => '🍎 Apple Music — یک‌ماهه', 'price' => 58000, 'platform' => 'apple',
              'desc' => 'اپل موزیک با کتابخانهٔ کامل و کیفیت Lossless.' ],

            // ── Video ──
            [ 'slug' => 'netflix-premium', 'name' => '🎬 Netflix Premium — یک‌ماهه', 'price' => 90000, 'platform' => 'netflix',
              'desc' => 'نتفلیکس پرمیوم با کیفیت 4K؛ مناسب تماشای فیلم و سریال بدون محدودیت.' ],
            [ 'slug' => 'youtube-premium', 'name' => '▶️ YouTube Premium — یک‌ماهه', 'price' => 38000, 'platform' => 'youtube',
              'desc' => 'یوتیوب پرمیوم بدون تبلیغ، پخش در پس‌زمینه و دانلود ویدیو.' ],
            [ 'slug' => 'twitch-turbo', 'name' => '🎮 Twitch Turbo — یک‌ماهه', 'price' => 70000, 'platform' => 'twitch',
              'desc' => 'توییچ توربو؛ تماشای استریم بدون تبلیغ و امکانات ویژه.' ],

            // ── Design / Productivity ──
            [ 'slug' => 'canva-pro', 'name' => '🖌️ Canva Pro — اشتراک یک‌ساله', 'price' => 55000, 'platform' => 'canva',
              'desc' => 'کانوا پرو با میلیون‌ها قالب، حذف پس‌زمینه و امکانات طراحی حرفه‌ای؛ اشتراک یک‌ساله.' ],
            [ 'slug' => 'capcut-pro', 'name' => '✂️ CapCut Pro — یک‌ماهه', 'price' => 48000, 'platform' => 'capcut',
              'desc' => 'کپ‌کات پرو برای تدوین حرفه‌ای ویدیو با افکت‌ها و امکانات ویژه.' ],
            [ 'slug' => 'linkedin-premium', 'name' => '💼 LinkedIn Premium — یک‌ماهه', 'price' => 120000, 'platform' => 'linkedin',
              'desc' => 'لینکدین پرمیوم برای دیده‌شدن بیشتر، InMail و دوره‌های آموزشی.' ],
            [ 'slug' => 'zoom-pro', 'name' => '📹 Zoom Pro — یک‌ماهه', 'price' => 98000, 'platform' => 'zoom',
              'desc' => 'زوم پرو برای جلسات طولانی بدون محدودیت زمان و امکانات مدیریت جلسه.' ],

            // ── Other ──
            [ 'slug' => 'discord-nitro', 'name' => '🕹️ Discord Nitro — یک‌ماهه', 'price' => 72000, 'platform' => 'discord',
              'desc' => 'دیسکورد نایترو با آپلود حجیم، ایموجی سفارشی و بوست سرور.' ],
            [ 'slug' => 'x-premium', 'name' => '✖️ X Premium — یک‌ماهه', 'price' => 88000, 'platform' => 'twitter',
              'desc' => 'اشتراک X Premium (توییتر) با تیک، ویرایش توییت و دسترسی‌های ویژه.' ],
            [ 'slug' => 'amazon-prime', 'name' => '📦 Amazon Prime — یک‌ماهه', 'price' => 95000, 'platform' => 'amazon',
              'desc' => 'آمازون پرایم با ارسال ویژه و دسترسی به Prime Video.' ],
        ];
    }
}
