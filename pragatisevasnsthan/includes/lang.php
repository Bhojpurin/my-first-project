<?php
/**
 * lang.php - Hindi / English switch for the PUBLIC site.
 * ?lang=hi or ?lang=en sets a cookie. Default = Hindi.
 *   t('donate_now')        -> fixed UI label in current language
 *   pick($row, 'title')    -> $row['title_hi'] if present, else $row['title_en']
 */
const LANGS = ['hi', 'en'];

function lang(): string
{
    static $l = null;
    if ($l === null) {
        $l = $_COOKIE['lang'] ?? 'hi';
        if (isset($_GET['lang']) && in_array($_GET['lang'], LANGS, true)) {
            $l = $_GET['lang'];
            if (!headers_sent()) {
                setcookie('lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax',
                                       'secure' => is_https(), 'httponly' => true]);
            }
        }
        if (!in_array($l, LANGS, true)) $l = 'hi';
    }
    return $l;
}

/** Bilingual DB column: pick($row,'title') reads title_hi/title_en with fallback to the other. */
function pick(array $row, string $field): string
{
    $a = (string) ($row[$field . '_' . lang()] ?? '');
    if ($a !== '') return $a;
    return (string) ($row[$field . '_en'] ?? $row[$field . '_hi'] ?? '');
}

function t(string $key): string
{
    static $d = [
        'home'        => ['होम', 'Home'],
        'about'       => ['हमारे बारे में', 'About'],
        'campaigns'   => ['अभियान', 'Campaigns'],
        'news'        => ['समाचार', 'News'],
        'contact'     => ['संपर्क', 'Contact'],
        'donate_now'  => ['अभी दान करें', 'Donate now'],
        'read_more'   => ['और पढ़ें', 'Read more'],
        'all_news'    => ['सभी समाचार', 'All news'],
        'all_campaigns' => ['सभी अभियान', 'All campaigns'],
        'latest_news' => ['ताज़ा समाचार', 'Latest news'],
        'our_campaigns' => ['हमारे अभियान', 'Our campaigns'],
        'raised'      => ['जुटाई गई राशि', 'Raised'],
        'goal'        => ['लक्ष्य', 'Goal'],
        'no_news'     => ['अभी कोई समाचार प्रकाशित नहीं है।', 'No news published yet.'],
        'no_campaigns'=> ['अभी कोई अभियान सक्रिय नहीं है।', 'No active campaigns yet.'],
        'hero_default_title' => ['सेवा से समाज में प्रगति', 'Progress through service'],
        'hero_default_sub'   => ['हर दान किसी ज़रूरतमंद के जीवन में बदलाव लाता है।', 'Every donation changes a life.'],
        'quick_links' => ['उपयोगी लिंक', 'Quick links'],
        'privacy'     => ['गोपनीयता नीति', 'Privacy policy'],
        'refund'      => ['दान और रिफंड नीति', 'Donation & refund policy'],
        'terms'       => ['नियम और शर्तें', 'Terms & conditions'],
        'reg_no'      => ['पंजीकरण सं.', 'Reg. No.'],
        'bank_details'=> ['बैंक विवरण', 'Bank details'],
        'online_soon' => ['ऑनलाइन भुगतान (UPI / कार्ड / नेटबैंकिंग) जल्द शुरू होगा। तब तक आप सीधे बैंक ट्रांसफ़र या UPI से सहयोग कर सकते हैं।',
                          'Online payment (UPI / card / netbanking) is coming soon. Until then you can donate by direct bank transfer or UPI.'],
        'back'        => ['वापस', 'Back'],
        'not_found'   => ['पेज नहीं मिला', 'Page not found'],
        'published'   => ['प्रकाशित', 'Published'],
    ];
    $i = lang() === 'hi' ? 0 : 1;
    return $d[$key][$i] ?? $key;
}
