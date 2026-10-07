<?php
/** volunteer.php - volunteer registration (saved in `volunteers`) */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$f = ['name' => '', 'mobile' => '', 'email' => '', 'city' => '', 'skills' => '', 'availability' => '', 'message' => '']; $errors = []; $sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name' => 120, 'mobile' => 20, 'email' => 150, 'city' => 100, 'skills' => 255, 'availability' => 120] as $k => $m) $f[$k] = post_str($k, $m);
    $f['message'] = post_str('message', 2000); $f['mobile'] = preg_replace('/\D+/', '', $f['mobile']);
    if (strlen($f['mobile']) === 12 && str_starts_with($f['mobile'], '91')) $f['mobile'] = substr($f['mobile'], 2);
    if (post_str('website', 50) !== '') $errors[] = 'Invalid request.';
    if (mb_strlen($f['name']) < 2) $errors[] = tx('कृपया अपना नाम लिखें।', 'Please enter your name.');
    if (!preg_match('/^[6-9]\d{9}$/', $f['mobile'])) $errors[] = tx('सही 10 अंकों का मोबाइल नंबर लिखें।', 'Enter a valid 10-digit mobile number.');
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = tx('सही ईमेल लिखें।', 'Enter a valid email.');
    if (!$errors && db_value('SELECT COUNT(*) FROM volunteers WHERE mobile = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$f['mobile']]))
        $errors[] = tx('इस नंबर से आज पहले ही पंजीकरण हो चुका है।', 'This number has already registered today.');
    if (!$errors) {
        db_query('INSERT INTO volunteers (name, mobile, email, city, skills, availability, message) VALUES (?,?,?,?,?,?,?)',
                 [$f['name'], $f['mobile'], $f['email'] ?: null, $f['city'] ?: null, $f['skills'] ?: null, $f['availability'] ?: null, $f['message'] ?: null]);
        $sent = true; $f = array_map(fn() => '', $f);
    }
}
$page_title = tx('स्वयंसेवक बनें', 'Become a volunteer'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:640px">
  <h1><?= e(tx('स्वयंसेवक बनें', 'Become a volunteer')) ?></h1>
  <form method="post" class="donate-box dform">
    <?php if ($sent): ?><div class="notice" style="background:var(--ok-bg);border-color:var(--ok)"><?= e(tx('धन्यवाद! हम जल्द आपसे संपर्क करेंगे।', 'Thank you! We will contact you soon.')) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert-err"><?= e($err) ?></div><?php endforeach; ?>
    <?= csrf_field() ?><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <div class="two"><div><label class="lbl"><?= e(tx('नाम', 'Name')) ?> *</label><input name="name" required maxlength="120" value="<?= e($f['name']) ?>"></div>
      <div><label class="lbl"><?= e(tx('मोबाइल', 'Mobile')) ?> *</label><input name="mobile" required inputmode="numeric" maxlength="14" value="<?= e($f['mobile']) ?>"></div>
      <div><label class="lbl">Email</label><input type="email" name="email" maxlength="150" value="<?= e($f['email']) ?>"></div>
      <div><label class="lbl"><?= e(tx('शहर', 'City')) ?></label><input name="city" maxlength="100" value="<?= e($f['city']) ?>"></div></div>
    <label class="lbl"><?= e(tx('कौशल / रुचि', 'Skills / interests')) ?></label><input name="skills" maxlength="255" value="<?= e($f['skills']) ?>">
    <label class="lbl"><?= e(tx('उपलब्धता', 'Availability')) ?></label><input name="availability" maxlength="120" placeholder="<?= e(tx('जैसे: रविवार', 'e.g. weekends')) ?>" value="<?= e($f['availability']) ?>">
    <label class="lbl"><?= e(tx('संदेश', 'Message')) ?></label><textarea name="message" rows="3" maxlength="2000" style="width:100%;padding:11px;border:1px solid var(--line);border-radius:10px;font:15px var(--font-body)"><?= e($f['message']) ?></textarea>
    <button class="btn btn-amber" type="submit" style="margin-top:12px"><?= e(tx('पंजीकरण करें', 'Register')) ?></button>
  </form>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
