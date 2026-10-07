<?php
/**
 * upload.php - safe file uploads.
 *
 *   $r = save_upload('image', 'news');          // images -> uploads/news/2026/10/<random>.webp
 *   if (!$r['ok']) { $errors[] = $r['error']; }
 *   elseif ($r['path']) { ... save $r['path'] in DB ... }   // path is NULL when no file was chosen
 *   delete_upload($oldPath);
 *
 * Safety: extension + real image check, random file name, images are re-encoded with GD
 * (kills hidden code inside images, strips EXIF), max 1600px wide, uploads/ never executes PHP.
 */

function upload_url(?string $rel): string
{
    return $rel ? url('uploads/' . $rel) : '';
}

function upload_fail(string $msg): array
{
    return ['ok' => false, 'path' => null, 'error' => $msg];
}

function save_upload(string $field, string $subdir, array $allowed = ['jpg', 'jpeg', 'png', 'webp'], int $maxBytes = 3145728): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'path' => null, 'error' => ''];

    if ($f['error'] !== UPLOAD_ERR_OK)  return upload_fail('Upload fail ho gaya (code ' . (int) $f['error'] . ').');
    if ($f['size'] > $maxBytes)         return upload_fail('File bahut badi hai (max ' . round($maxBytes / 1048576, 1) . ' MB).');
    if (!is_uploaded_file($f['tmp_name'])) return upload_fail('Invalid upload.');

    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return upload_fail('Is type ki file allowed nahi hai. Allowed: ' . implode(', ', $allowed));
    if (!preg_match('/^[a-z0-9_\-]+$/', $subdir)) throw new InvalidArgumentException('bad subdir');

    $relDir = $subdir . '/' . date('Y') . '/' . date('m');
    $absDir = ROOT_PATH . '/uploads/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true)) return upload_fail('Upload folder nahi ban paya.');
    $name = bin2hex(random_bytes(10));

    // ---------- images ----------
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return upload_fail('Sahi image file chunein (JPG, PNG ya WebP).');
        }
        if ($info[0] * $info[1] > 40000000) return upload_fail('Image ka size (pixels) bahut bada hai.');

        $type = $info[2];
        $outExt = $type === IMAGETYPE_PNG ? 'png' : ($type === IMAGETYPE_WEBP ? 'webp' : 'jpg');

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
            if (!$img) return upload_fail('Image read nahi ho payi.');

            // phone photos: apply EXIF rotation before EXIF is stripped
            if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($f['tmp_name']);
                $deg = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
                if ($deg) { $rot = imagerotate($img, $deg, 0); if ($rot) { imagedestroy($img); $img = $rot; } }
            }
            if (function_exists('imagepalettetotruecolor')) imagepalettetotruecolor($img);

            $w = imagesx($img); $h = imagesy($img);
            if ($w > 1600) {
                $nh = (int) round($h * 1600 / $w);
                $res = imagecreatetruecolor(1600, $nh);
                imagealphablending($res, false); imagesavealpha($res, true);
                imagecopyresampled($res, $img, 0, 0, 0, 0, 1600, $nh, $w, $h);
                imagedestroy($img); $img = $res;
            }
            imagesavealpha($img, true);

            if (function_exists('imagewebp'))      { $outExt = 'webp'; $ok = imagewebp($img, "$absDir/$name.webp", 82); }
            elseif ($type === IMAGETYPE_PNG)       { $outExt = 'png';  $ok = imagepng($img, "$absDir/$name.png", 8); }
            else                                   { $outExt = 'jpg';  $ok = imagejpeg($img, "$absDir/$name.jpg", 85); }
            imagedestroy($img);
            if (!$ok) return upload_fail('Image save nahi ho payi.');
        } else {
            if (!move_uploaded_file($f['tmp_name'], "$absDir/$name.$outExt")) return upload_fail('File save nahi ho payi.');
        }
        return ['ok' => true, 'path' => "$relDir/$name.$outExt", 'error' => ''];
    }

    // ---------- PDF ----------
    if ($ext === 'pdf') {
        if (file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') return upload_fail('Ye valid PDF nahi hai.');
        if (!move_uploaded_file($f['tmp_name'], "$absDir/$name.pdf")) return upload_fail('File save nahi ho payi.');
        return ['ok' => true, 'path' => "$relDir/$name.pdf", 'error' => ''];
    }

    return upload_fail('Ye file type abhi supported nahi hai.');
}

/** Delete a file we saved earlier. Only paths of the exact shape we generate are accepted. */
function delete_upload(?string $rel): void
{
    if (!$rel || !preg_match('#^[a-z0-9_\-]+/\d{4}/\d{2}/[a-f0-9]+\.(webp|jpg|png|pdf)$#', $rel)) return;
    $file = ROOT_PATH . '/uploads/' . $rel;
    if (is_file($file)) @unlink($file);
}
