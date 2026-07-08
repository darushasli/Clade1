<?php
/**
 * Curated premium-account catalogue used to seed WooCommerce products for the
 * "اکانت پرمیوم" section (no external API). Each item has a category group, a
 * brand icon (from the theme assets), an SEO-rich description and a feature
 * list. Prices are in Toman and are starting points — edit freely after seeding.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Account_Seed {

    /** group machine → Persian label (becomes a product sub-category). */
    public static function groups(): array {
        return [
            'ai'     => 'اکانت هوش مصنوعی',
            'music'  => 'اکانت موزیک',
            'video'  => 'اکانت فیلم و سریال',
            'design' => 'اکانت طراحی و بهره‌وری',
            'other'  => 'سایر اکانت‌ها',
        ];
    }

    /**
     * @return array<int,array{slug:string,name:string,desc:string,price:int,platform:string,group:string,icon:string,features:array}>
     */
    public static function items(): array {
        return [
            /* ── هوش مصنوعی ── */
            [ 'slug' => 'chatgpt-plus', 'name' => '🤖 اکانت ChatGPT Plus (چت‌جی‌پی‌تی پلاس)', 'price' => 165000, 'platform' => 'openai', 'group' => 'ai', 'icon' => 'ai/chatgpt.svg',
              'features' => [ 'دسترسی به مدل‌های پیشرفته GPT', 'سرعت بالا و اولویت در ساعات شلوغ', 'تولید تصویر و تحلیل فایل', 'تحویل آنی و پشتیبانی کامل' ],
              'desc' => 'خرید اکانت ChatGPT Plus با تحویل آنی و قیمت مناسب. با اشتراک یک‌ماههٔ چت‌جی‌پی‌تی پلاس به جدیدترین مدل‌های هوش مصنوعی OpenAI، سرعت پاسخ‌دهی بالا، تولید تصویر و تحلیل فایل دسترسی پیدا می‌کنید. مناسب برنامه‌نویسان، تولیدکنندگان محتوا، دانشجویان و کسب‌وکارها. تمام اکانت‌ها اصل و با ضمانت هستند و در صورت بروز مشکل جایگزین می‌شوند.' ],
            [ 'slug' => 'claude-pro', 'name' => '🧠 اکانت Claude Pro (کلود پرو)', 'price' => 180000, 'platform' => 'anthropic', 'group' => 'ai', 'icon' => 'ai/claude.svg',
              'features' => [ 'دسترسی به مدل‌های پیشرفته Claude', 'محدودیت استفادهٔ بسیار بالاتر', 'تحلیل اسناد طولانی', 'ایده‌آل برای کارهای حرفه‌ای' ],
              'desc' => 'خرید اکانت Claude Pro شرکت Anthropic با تحویل فوری. کلود پرو یکی از قدرتمندترین دستیارهای هوش مصنوعی برای نوشتن، برنامه‌نویسی، تحلیل اسناد طولانی و پاسخ‌های دقیق است. با اشتراک ماهانه از محدودیت استفادهٔ بالاتر و دسترسی به بهترین مدل‌ها بهره‌مند می‌شوید. اصل، تضمینی و با پشتیبانی ۲۴ ساعته.' ],
            [ 'slug' => 'gemini-advanced', 'name' => '✨ اکانت Gemini Advanced (جمینی)', 'price' => 155000, 'platform' => 'google', 'group' => 'ai', 'icon' => 'ai/gemini.svg',
              'features' => [ 'قوی‌ترین مدل هوش مصنوعی گوگل', 'یکپارچه با سرویس‌های گوگل', 'تحلیل تصویر و متن', '۲ ترابایت فضای ابری' ],
              'desc' => 'خرید اکانت Gemini Advanced گوگل با بهترین قیمت. جمینی ادونس به قدرتمندترین مدل‌های هوش مصنوعی گوگل، یکپارچگی با Gmail و Docs و فضای ابری ۲ ترابایتی دسترسی می‌دهد. مناسب کاربران حرفه‌ای و کسب‌وکارها. تحویل سریع و تضمین اصالت.' ],
            [ 'slug' => 'perplexity-pro', 'name' => '🔎 اکانت Perplexity Pro (پرپلکسیتی)', 'price' => 140000, 'platform' => 'perplexity', 'group' => 'ai', 'icon' => 'ai/perplexity.svg',
              'features' => [ 'موتور جستجوی هوش مصنوعی', 'پاسخ‌های منبع‌دار و دقیق', 'دسترسی به مدل‌های متعدد', 'اشتراک سالانه مقرون‌به‌صرفه' ],
              'desc' => 'خرید اکانت Perplexity Pro؛ موتور جستجوی مبتنی بر هوش مصنوعی که پاسخ‌های دقیق و منبع‌دار ارائه می‌دهد. با اشتراک سالانهٔ پرپلکسیتی پرو به جستجوی نامحدود، مدل‌های پیشرفته و تحلیل فایل دسترسی دارید. تحویل آنی و ضمانت اصالت.' ],
            [ 'slug' => 'grok-super', 'name' => '⚡ اکانت Grok / X Premium (گروک)', 'price' => 160000, 'platform' => 'twitter', 'group' => 'ai', 'icon' => 'ai/grok.svg',
              'features' => [ 'دسترسی به هوش مصنوعی Grok', 'امکانات کامل X Premium', 'تیک تأیید و ویرایش پست', 'کاهش تبلیغات' ],
              'desc' => 'خرید اکانت Grok به‌همراه X Premium با تحویل فوری. گروک هوش مصنوعی شبکهٔ ایکس (توییتر) است و با این اشتراک علاوه بر دسترسی به گروک، از تیک تأیید، ویرایش پست و کاهش تبلیغات هم بهره‌مند می‌شوید. اصل و تضمینی.' ],
            [ 'slug' => 'midjourney', 'name' => '🎨 اکانت Midjourney (میدجرنی)', 'price' => 210000, 'platform' => 'midjourney', 'group' => 'ai', 'icon' => 'ai/midjourney.svg',
              'features' => [ 'ساخت تصاویر هنری باکیفیت', 'ساعت GPU اختصاصی', 'خروجی تجاری', 'مناسب طراحان و هنرمندان' ],
              'desc' => 'خرید اکانت Midjourney برای ساخت تصاویر هنری خیره‌کننده با هوش مصنوعی. پلن استاندارد میدجرنی با ساعت GPU اختصاصی و مجوز استفادهٔ تجاری، انتخابی عالی برای طراحان، تبلیغات و تولید محتوای بصری است. تحویل سریع و پشتیبانی کامل.' ],
            [ 'slug' => 'copilot-pro', 'name' => '💻 اکانت Copilot Pro (کوپایلوت)', 'price' => 190000, 'platform' => 'microsoft', 'group' => 'ai', 'icon' => 'ai/copilot.svg',
              'features' => [ 'دستیار هوشمند مایکروسافت', 'یکپارچه با Office', 'کمک‌برنامه‌نویسی', 'اولویت دسترسی به مدل‌ها' ],
              'desc' => 'خرید اکانت Microsoft Copilot Pro با تحویل آنی. کوپایلوت پرو دستیار هوش مصنوعی مایکروسافت برای افزایش بهره‌وری، یکپارچه با Word و Excel و PowerPoint و ابزار قدرتمند برنامه‌نویسی است. اصل و با ضمانت.' ],

            /* ── موزیک ── */
            [ 'slug' => 'spotify-premium', 'name' => '🎧 اکانت Spotify Premium (اسپاتیفای)', 'price' => 45000, 'platform' => 'spotify', 'group' => 'music', 'icon' => 'iconpack/spotify.svg',
              'features' => [ 'پخش موزیک بدون تبلیغ', 'دانلود و پخش آفلاین', 'کیفیت صدای بالا', 'تحویل روی اکانت شما یا آماده' ],
              'desc' => 'خرید اشتراک اسپاتیفای پرمیوم با ارزان‌ترین قیمت و تحویل فوری. با Spotify Premium میلیون‌ها آهنگ را بدون تبلیغ، با کیفیت بالا و به‌صورت آفلاین گوش دهید. قابل فعال‌سازی روی اکانت خودتان یا تحویل اکانت آماده. تضمینی و با پشتیبانی.' ],
            [ 'slug' => 'youtube-music', 'name' => '🎵 اکانت YouTube Music (یوتیوب موزیک)', 'price' => 47000, 'platform' => 'youtube', 'group' => 'music', 'icon' => 'iconpack/youtube.svg',
              'features' => [ 'پخش بدون تبلیغ', 'دانلود آهنگ', 'پخش در پس‌زمینه', 'کتابخانهٔ عظیم موزیک' ],
              'desc' => 'خرید اشتراک YouTube Music Premium با تحویل سریع. یوتیوب موزیک پرمیوم امکان پخش بدون تبلیغ، دانلود آهنگ و پخش در پس‌زمینه را فراهم می‌کند. اصل و تضمینی با قیمت مناسب.' ],
            [ 'slug' => 'apple-music', 'name' => '🍎 اکانت Apple Music (اپل موزیک)', 'price' => 58000, 'platform' => 'apple', 'group' => 'music', 'icon' => 'iconpack/apple.svg',
              'features' => [ 'کیفیت Lossless و Dolby Atmos', 'کتابخانهٔ کامل آهنگ‌ها', 'بدون تبلیغ', 'دانلود آفلاین' ],
              'desc' => 'خرید اشتراک اپل موزیک با تحویل فوری. Apple Music با کیفیت Lossless و صدای فضایی Dolby Atmos، دسترسی به میلیون‌ها آهنگ بدون تبلیغ را ممکن می‌کند. اصل، تضمینی و با پشتیبانی کامل.' ],

            /* ── فیلم و سریال ── */
            [ 'slug' => 'netflix-premium', 'name' => '🎬 اکانت Netflix Premium (نتفلیکس)', 'price' => 90000, 'platform' => 'netflix', 'group' => 'video', 'icon' => 'new/netflix.svg',
              'features' => [ 'کیفیت 4K UHD', 'تماشا روی چند دستگاه', 'کتابخانهٔ کامل فیلم و سریال', 'بدون محدودیت' ],
              'desc' => 'خرید اشتراک نتفلیکس پرمیوم با کیفیت 4K و تحویل فوری. با Netflix Premium جدیدترین فیلم‌ها و سریال‌ها را با بالاترین کیفیت و روی چند دستگاه تماشا کنید. اصل، تضمینی و با پشتیبانی ۲۴ ساعته.' ],
            [ 'slug' => 'youtube-premium', 'name' => '▶️ اکانت YouTube Premium (یوتیوب پرمیوم)', 'price' => 38000, 'platform' => 'youtube', 'group' => 'video', 'icon' => 'iconpack/youtube.svg',
              'features' => [ 'تماشا بدون تبلیغ', 'پخش در پس‌زمینه', 'دانلود ویدیو', 'شامل YouTube Music' ],
              'desc' => 'خرید اشتراک YouTube Premium با ارزان‌ترین قیمت. یوتیوب پرمیوم تماشای بدون تبلیغ، پخش در پس‌زمینه و دانلود ویدیو را ممکن می‌کند و شامل یوتیوب موزیک هم می‌شود. تحویل سریع و تضمینی.' ],
            [ 'slug' => 'twitch-turbo', 'name' => '🎮 اکانت Twitch Turbo (توییچ)', 'price' => 70000, 'platform' => 'twitch', 'group' => 'video', 'icon' => 'iconpack/twitch.svg',
              'features' => [ 'تماشای استریم بدون تبلیغ', 'نشان ویژه', 'ذخیرهٔ طولانی‌تر ویدیوها', 'ایموجی اختصاصی' ],
              'desc' => 'خرید اشتراک Twitch Turbo با تحویل فوری. توییچ توربو تجربهٔ تماشای استریم بدون تبلیغ، نشان ویژه و امکانات اختصاصی را برای گیمرها فراهم می‌کند. اصل و با ضمانت.' ],

            /* ── طراحی و بهره‌وری ── */
            [ 'slug' => 'canva-pro', 'name' => '🖌️ اکانت Canva Pro (کانوا پرو)', 'price' => 55000, 'platform' => 'canva', 'group' => 'design', 'icon' => 'new/canva-icon.svg',
              'features' => [ 'میلیون‌ها قالب و عنصر آماده', 'حذف پس‌زمینه با یک کلیک', 'فضای ذخیرهٔ ابری بالا', 'اشتراک یک‌ساله' ],
              'desc' => 'خرید اشتراک Canva Pro یک‌ساله با قیمت استثنایی. کانوا پرو با میلیون‌ها قالب حرفه‌ای، ابزار حذف پس‌زمینه، برندکیت و فضای ابری بالا، طراحی گرافیک را برای همه ساده می‌کند. مناسب مدیران شبکه‌های اجتماعی و کسب‌وکارها. تحویل سریع و تضمینی.' ],
            [ 'slug' => 'capcut-pro', 'name' => '✂️ اکانت CapCut Pro (کپ‌کات)', 'price' => 48000, 'platform' => 'capcut', 'group' => 'design', 'icon' => 'new/capcut-icon.svg',
              'features' => [ 'افکت‌ها و ترنزیشن‌های ویژه', 'حذف پس‌زمینه ویدیو', 'خروجی بدون واترمارک', 'فضای ابری' ],
              'desc' => 'خرید اشتراک CapCut Pro برای تدوین حرفه‌ای ویدیو. کپ‌کات پرو با افکت‌ها، ترنزیشن‌ها و ابزارهای پیشرفتهٔ ویرایش، مناسب سازندگان محتوای تیک‌تاک و اینستاگرام است. خروجی بدون واترمارک و تحویل فوری.' ],
            [ 'slug' => 'linkedin-premium', 'name' => '💼 اکانت LinkedIn Premium (لینکدین)', 'price' => 120000, 'platform' => 'linkedin', 'group' => 'design', 'icon' => 'iconpack/linkedin.svg',
              'features' => [ 'دیده‌شدن بیشتر پروفایل', 'ارسال InMail', 'دوره‌های LinkedIn Learning', 'اطلاعات رقبا و بازار' ],
              'desc' => 'خرید اشتراک LinkedIn Premium با تحویل سریع. لینکدین پرمیوم به شما کمک می‌کند بیشتر دیده شوید، مستقیم با کارفرمایان پیام دهید و به هزاران دورهٔ آموزشی دسترسی داشته باشید. مناسب متخصصان و جویندگان کار. اصل و تضمینی.' ],
            [ 'slug' => 'zoom-pro', 'name' => '📹 اکانت Zoom Pro (زوم پرو)', 'price' => 98000, 'platform' => 'zoom', 'group' => 'design', 'icon' => 'iconpack/zoom.svg',
              'features' => [ 'جلسات بدون محدودیت زمان', 'تا ۱۰۰ شرکت‌کننده', 'ضبط ابری', 'مدیریت پیشرفتهٔ جلسه' ],
              'desc' => 'خرید اشتراک Zoom Pro برای جلسات آنلاین حرفه‌ای. زوم پرو محدودیت ۴۰ دقیقه را برمی‌دارد و امکان برگزاری جلسات طولانی با ضبط ابری و مدیریت پیشرفته را می‌دهد. مناسب کسب‌وکارها و مدرسان. تحویل فوری.' ],

            /* ── سایر ── */
            [ 'slug' => 'discord-nitro', 'name' => '🕹️ اکانت Discord Nitro (دیسکورد نایترو)', 'price' => 72000, 'platform' => 'discord', 'group' => 'other', 'icon' => 'iconpack/discord.svg',
              'features' => [ 'آپلود فایل حجیم', 'ایموجی و استیکر سفارشی', 'بوست سرور', 'کیفیت استریم بالاتر' ],
              'desc' => 'خرید اشتراک Discord Nitro با تحویل فوری. دیسکورد نایترو امکان آپلود فایل‌های حجیم، ایموجی سفارشی، بوست سرور و کیفیت استریم بالاتر را فراهم می‌کند. مناسب گیمرها و جامعه‌های آنلاین. اصل و تضمینی.' ],
            [ 'slug' => 'x-premium', 'name' => '✖️ اکانت X Premium (توییتر)', 'price' => 88000, 'platform' => 'twitter', 'group' => 'other', 'icon' => 'iconpack/x.svg',
              'features' => [ 'تیک تأیید آبی', 'ویرایش پست', 'کاهش تبلیغات', 'پست‌های طولانی‌تر' ],
              'desc' => 'خرید اشتراک X Premium (توییتر بلو) با تحویل فوری. با ایکس پریمیوم تیک تأیید دریافت می‌کنید، پست‌ها را ویرایش می‌کنید و تبلیغات کمتری می‌بینید. اصل، تضمینی و با پشتیبانی.' ],
            [ 'slug' => 'amazon-prime', 'name' => '📦 اکانت Amazon Prime (آمازون پرایم)', 'price' => 95000, 'platform' => 'amazon', 'group' => 'other', 'icon' => 'iconpack/amazon.svg',
              'features' => [ 'دسترسی به Prime Video', 'ارسال ویژه', 'Prime Music', 'تخفیف‌های اختصاصی' ],
              'desc' => 'خرید اشتراک Amazon Prime با تحویل فوری. آمازون پرایم شامل Prime Video، Prime Music، ارسال ویژه و تخفیف‌های اختصاصی است. اصل و با ضمانت اصالت.' ],
        ];
    }
}
