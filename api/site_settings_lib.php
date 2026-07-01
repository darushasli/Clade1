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

    // index.html — hero
    'idx_brand_tagline' => 'همیشه همراه رشد کانال شما',
    'idx_hero_badge' => 'نسل جدید افزایش اعضای کانال‌های تلگرامی',
    'idx_hero_title' => 'انفجار آمار کانال با',
    'idx_hero_title_em' => 'ممبر واقعی آپلودری',
    'idx_hero_subtitle' => 'کاربرانی که برای دانلود فایل‌های مورد نیاز خود وارد کانال شما می‌شوند. ماندگاری خیره‌کننده، نرخ تعامل بالا و بدون ریزش ناگهانی. کانال خود را به هاب دانلود تبدیل کنید.',
    'idx_cta_primary' => 'مشاهده قیمت‌ها و خرید',
    'idx_cta_secondary' => 'بررسی نحوه کارکرد',
    'idx_stat1_label' => 'تعداد جذب موفق',
    'idx_stat2_label' => 'رضایت مشتریان',
    'idx_stat3_label' => 'نرخ ماندگاری اعضا',

    // index.html — trust marquee
    'idx_trust_1' => 'بدون ریسک مسدودی',
    'idx_trust_2' => 'تحویل آنی سفارش',
    'idx_trust_3' => 'ممبر ۱۰۰٪ واقعی',
    'idx_trust_4' => 'پشتیبانی ۲۴ ساعته',
    'idx_trust_5' => 'ضمانت بازگشت وجه',
    'idx_trust_6' => 'ریزش نزدیک به صفر',

    // index.html — how it works
    'idx_how_title' => 'نحوه کار سیستم؛ از جستجوی فایل تا رشد کانال',
    'idx_how_subtitle' => 'یک مسیر سه‌مرحله‌ای ساده که عضو واقعی و باکیفیت را به کانال شما متصل می‌کند.',
    'idx_step1_title' => 'کاربر دنبال فایل می‌گردد',
    'idx_step1_desc' => 'کاربری در ربات‌های آپلودر به‌دنبال فیلم، نرم‌افزار یا فایل آموزشی مدنظرش است و لینک دانلود را پیدا می‌کند.',
    'idx_step2_title' => 'عضویت، شرط باز شدن قفل',
    'idx_step2_desc' => 'ربات برای آزادسازی فایل، عضویت در کانال شما را به‌عنوان مرحله‌ی الزامی پیش‌روی کاربر قرار می‌دهد.',
    'idx_step3_title' => 'کانال شما رشد می‌کند',
    'idx_step3_desc' => 'عضو جدید برای دریافت فایل‌های بعدی در کانال باقی می‌ماند و آمار واقعی و پایدار شما را بالا می‌برد.',

    // index.html — bento features
    'idx_bento_title' => 'مهندسی جذب؛ چرا ممبر آپلودری بی‌رقیب است؟',
    'idx_bento_subtitle' => 'تفاوت ساختاری این متد با روش‌های اجباری و پاپ‌آپ در کیفیت و حق انتخاب کاربر نهفته است.',
    'idx_feature1_title' => 'مکانیسم جذب مبتنی بر نیاز واقعی',
    'idx_feature1_desc' => 'در این روش کاربران از طریق ربات‌های جستجوی فایل، فیلم یا فایل آموزشی اقدام به دانلود می‌کنند و ربات آنها را ملزم به عضویت در کانال اسپانسر (شما) می‌کند. از آنجا که کاربر به فایل نیاز مبرم دارد، با اشتیاق عضو شده و به دلیل فعال بودن حساب کاربری، بالاترین میزان سین (View) را برای پست‌های شما به ارمغان می‌آورد.',
    'idx_feature2_title' => 'کاملاً امن و ضد دلیلت',
    'idx_feature2_desc' => 'الگوریتم‌های تزریق ممبر ما مطابق با استانداردهای سخت‌گیرانه تلگرام تنظیم شده‌اند؛ بدون قفل شدن کانال یا جریمه‌های ریپورت.',
    'idx_feature3_title' => 'ریزش بسیار ناچیز',
    'idx_feature3_desc' => 'به دلیل اینکه فرایند دانلود ممکن است در بازه‌های زمانی مختلف تکرار شود، اعضا کانال شما را مانیتور کرده و نرخ ریزش به حداقلِ ممکن در مارکت می‌رسد.',
    'idx_feature4_title' => 'سرعت تحویل آنی و هوشمند',
    'idx_feature4_desc' => 'سیستم به محض ثبت سفارش فعال شده و ممبرها را به صورت یکنواخت و طبیعی به کانال تزریق می‌کند تا ساختار آماری کانال کاملاً استاندارد و حرفه‌ای به نظر برسد.',

    // index.html — products + faq
    'idx_products_badge' => 'لیست قیمت شفاف و اقتصادی',
    'idx_products_title' => 'محصولات و پلن‌های ممبر تلگرام',
    'idx_faq_title' => 'پاسخ به سوالات شما در یک نگاه',
    'idx_faq_subtitle' => 'شفافیت کامل در ارائه خدمات، خط قرمز پلتفرم ماست.',
    'idx_faq1_q' => 'آیا ممبرهای آپلودری فیک یا ربات هستند؟',
    'idx_faq1_a' => 'خیر، به هیچ وجه. این ممبرها ۱۰۰٪ اکانت‌های واقعی کاربران فعال تلگرام هستند که برای باز شدن لینک دانلود یک فایل (فیلم، جزوه، پادکست، نرم‌افزار و...) در ربات‌های آپلودر، مجبور به عضویت در کانال شما شده‌اند. آنها اکانت‌های فعالی هستند که چت می‌کنند، پست‌ها را می‌بینند و استوری‌ها را دنبال می‌کنند.',
    'idx_faq2_q' => 'میزان ریزش این نوع ممبر چقدر است؟',
    'idx_faq2_a' => 'به دلیل ماهیت اختیاری-اجباری و نیاز کاربر به فایل‌های بعدی ربات، ریزش این متد نسبت به متدهای پاپ‌آپ و اد اجباری بسیار کمتر است (بین ۱۰ الی ۲۵ درصد بسته به جذابیت و موضوع کانال شما). اگر محتوای کانال شما باکیفیت باشد، این نرخ به حداقل می‌رسد.',
    'idx_faq3_q' => 'چه مدت طول می‌کشد تا سفارش تکمیل شود؟',
    'idx_faq3_a' => 'فرایند آغاز سفارش به صورت کاملاً آنی و سیستمی انجام می‌شود. تکمیل سفارشات با توجه به حجم پلن انتخابی شما بین ۲ الی ۲۴ ساعت زمان خواهد برد تا روند کاملاً ارگانیک و ایمن پیش برود.',

    // about.html
    'about_hero_title' => 'تیمی که رشد واقعی شبکه‌های اجتماعی شما را جدی می‌گیرد',
    'about_hero_desc' => 'آپلود گرام از دل نیاز واقعی مدیران کانال و پیج به آماری قابل‌اعتماد متولد شد. امروز با ارائه خدمات افزایش فالوور و بازدید واقعی اینستاگرام، ممبر تلگرام، یوتیوب، ساندکلاد و اسپاتیفای، کنار صدها کسب‌وکار و تولیدکننده محتوا ایستاده‌ایم.',
    'about_mission_title' => 'رشد واقعی، بدون ریسک و بدون پیچیدگی',
    'about_mission_p1' => 'هدف آپلود گرام ساده است: کمک به رشد واقعی و پایدار صفحه‌ها و کانال‌های شما، با کاربرانی که واقعاً وجود دارند و واقعاً تعامل می‌کنند. ما به جای فروش عدد خام، تجربه‌ای امن و شفاف از خرید آنلاین ارائه می‌دهیم؛ از انتخاب پلن تا تحویل و پشتیبانی پس از سفارش.',
    'about_mission_p2' => 'تمرکز ما روی پنج پلتفرم پراستفاده در ایران و جهان است: اینستاگرام، تلگرام، یوتیوب، ساندکلاد و اسپاتیفای. این یعنی هر کسب‌وکار، اینفلوئنسر یا تولیدکننده محتوا می‌تواند بدون نیاز به چند سرویس پراکنده، تمام نیاز رشد شبکه‌های اجتماعی خود را از یک‌جا تامین کند.',
    'about_process_title' => 'فرایند کار به زبان ساده',
    'about_process_subtitle' => 'چهار قدم تا رشد واقعی صفحه شما',
    'about_step1_title' => 'انتخاب پلن مناسب',
    'about_step1_desc' => 'پلتفرم و حجم مناسب کسب‌وکار خود را از میان محصولات انتخاب کنید.',
    'about_step2_title' => 'ثبت سفارش امن',
    'about_step2_desc' => 'بدون نیاز به رمز عبور؛ فقط با لینک یا نام کاربری پیج/کانال شما.',
    'about_step3_title' => 'شروع تحویل آنی',
    'about_step3_desc' => 'فرایند بلافاصله بعد از تایید پرداخت آغاز و به‌صورت تدریجی تکمیل می‌شود.',
    'about_step4_title' => 'پایش و پشتیبانی',
    'about_step4_desc' => 'تیم پشتیبانی تا تکمیل کامل سفارش، در تلگرام پاسخگوی شماست.',
    'about_values_title' => 'ارزش‌هایی که به آن متعهدیم',
    'about_cta_title' => 'آماده‌اید رشد واقعی را شروع کنید؟',
    'about_cta_subtitle' => 'همین حالا پلن مناسب پلتفرم خود را انتخاب کنید.',

    // products.html
    'prd_hero_title' => 'خرید فالوور و بازدید واقعی اینستاگرام، ممبر تلگرام، یوتیوب، ساندکلاد و اسپاتیفای',
    'prd_hero_subtitle' => 'آپلود گرام پلتفرم یکپارچه افزایش آمار شبکه‌های اجتماعی است؛ از پیج اینستاگرام و کانال تلگرام تا کانال یوتیوب، پروفایل ساندکلاد و اسپاتیفای. همه با کاربران واقعی، تحویل سریع و قیمت شفاف؛ بدون نیاز به رمز عبور یا دسترسی به اکانت شما.',
    'prd_why_title' => 'چرا آپلود گرام را انتخاب کنیم؟',
    'prd_cta_title' => 'همین حالا رشد واقعی صفحه خود را شروع کنید',
    'prd_cta_subtitle' => 'انتخاب پلن، پرداخت و شروع تحویل، کمتر از ۲ دقیقه طول می‌کشد.',

    // layout / appearance toggles (section visibility — theme/colors unaffected)
    'show_section_ecosystem' => '1',
    'show_section_faq' => '1',
    'show_section_trust_marquee' => '1',
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
