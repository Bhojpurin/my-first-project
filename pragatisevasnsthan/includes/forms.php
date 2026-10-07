<?php
/**
 * forms.php - helpers for admin forms and lists:
 * reading POST safely, slugs, pagination, text -> HTML.
 */

/** Cut a string to $max characters (safe for Hindi / UTF-8). */
function str_limit(string $s, int $max): string
{
    if ($max <= 0) return $s;
    return preg_match('/^.{0,' . $max . '}/us', $s, $m) ? $m[0] : '';
}

function post_str(string $key, int $max = 255): string
{
    return str_limit(trim((string) ($_POST[$key] ?? '')), $max);
}

function get_str(string $key, int $max = 100): string
{
    return str_limit(trim((string) ($_GET[$key] ?? '')), $max);
}

/** "My First News!" -> "my-first-news". Hindi-only text gives '' (caller adds a fallback). */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Make slug unique inside a table: news, news-2, news-3 ... ($table is internal, never user input) */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    if (!preg_match('/^[a-z_]+$/', $table)) throw new InvalidArgumentException('bad table');
    $base = $slug;
    $n = 1;
    while (db_value("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $ignoreId])) {
        $n++;
        $slug = $base . '-' . $n;
    }
    return $slug;
}

/** Pagination numbers: ['page','pages','offset','per','total'] */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

/** Pager links. $query = current filters (q, status ...) to keep while paging. */
function pager_html(array $p, array $query = []): string
{
    if ($p['pages'] <= 1) return '';
    $out = '<nav class="pager">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i !== 1 && $i !== $p['pages'] && abs($i - $p['page']) > 2) {
            if (abs($i - $p['page']) === 3) $out .= '<span>…</span>';
            continue;
        }
        $qs = http_build_query(array_filter($query + ['page' => $i], fn($v) => $v !== '' && $v !== null));
        $out .= '<a class="' . ($i === $p['page'] ? 'cur' : '') . '" href="?' . e($qs) . '">' . $i . '</a>';
    }
    return $out . '</nav>';
}

/** Plain text -> safe HTML paragraphs (blank line = new paragraph). Use on the public site. */
function text_to_html(?string $s): string
{
    $s = trim((string) $s);
    if ($s === '') return '';
    $out = '';
    foreach (preg_split('/\R{2,}/', $s) as $para) {
        $out .= '<p>' . nl2br(e(trim($para))) . '</p>';
    }
    return $out;
}

/** Parse <input type=datetime-local> value to 'Y-m-d H:i:s' (falls back to now). */
function parse_datetime(string $v): string
{
    $ts = $v === '' ? false : strtotime(str_replace('T', ' ', $v));
    return date('Y-m-d H:i:s', $ts ?: time());
}
