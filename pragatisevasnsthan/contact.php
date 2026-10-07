<?php
/** contact.php - contact details + message form (saved in `enquiries`) */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$f = ['name' => '', 'email' => '', 'mobile' => '', 'subject' => '', 'message' => '']; $errors = []; $sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name' => 120, 'email' => 150, 'mobile' => 20, 'subject' => 200] as $k => $m) $f[$k] = post_str($k, $m);
    $f['message'] = post_str('message', 3000);
    $f['mobile'] = preg_replace('/\D+/', '', $f['mobile']);
    if (post_str('website', 50) !== '') $errors[] = 'Invalid request.';
    if (mb_strlen($f['name']) < 2) $errors[] = tx('कृपया अपना नाम लिखें।', 'Please enter your name.');
    if ($f['email'] === '' && $f['mobile'] === '') $errors[] = tx('ईमेल या मोबाइल में से एक लिखें।', 'Enter an email or a mobile number.');
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = tx('सही ईमेल लिखें।', 'Enter a valid email.');
    if ($f['mobile'] !== '' && !preg_match('/^\d{10,12}$/', $f['mobile'])) $errors[] = tx('सही मोबाइल नंबर लिखें।', 'Enter a valid mobile number.');
    if (mb_strlen($f['message']) < 5) $errors[] = tx('कृपया संदेश लिखें।', 'Please write your message.');
    if (!$errors && (int) db_value('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [client_ip()]) >= 5)
        $errors[] = tx('बहुत अधिक संदेश भेजे गए, बाद में कोशिश करें।', 'Too many messages, please try later.');
    if (!$errors) {
        db_query('INSERT INTO enquiries (name, email, mobile, subject, message, ip) VALUES (?,?,?,?,?,?)',
                 [$f['name'], $f['email'] ?: null, $f['mobile'] ?: null, $f['subject'] ?: null, $f['message'], client_ip()]);
        $sent = true; $f = array_map(fn() => '', $f);
    }
}
$page_title = t('contact'); $nav_active = 'contact';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:860px">
  <h1><?= e(t('contact')) ?></h1>
  <div class="two-col">
    <div class="donate-box">
      <?php if (setting('address') !== ''): ?><p><strong><?= e(tx('पता', 'Address')) ?></strong><br><?= nl2br(e(setting('address'))) ?></p><?php endif; ?>
      <?php if (setting('contact_phone') !== ''): ?><p><strong><?= e(tx('फ़ोन', 'Phone')) ?></strong><br><?= e(setting('contact_phone')) ?></p><?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?><p><strong>Email</strong><br><?= e(setting('contact_email')) ?></p><?php endif; ?>
      <p><a class="btn btn-ghost" href="<?= e(url('volunteer.php')) ?>"><?= e(tx('स्वयंसेवक बनें', 'Become a volunteer')) ?></a></p>
    </div>
    <form method="post" class="donate-box dform">
      <?php if ($sent): ?><div class="notice" style="background:var(--ok-bg);border-color:var(--ok)"><?= e(tx('धन्यवाद! आपका संदेश मिल गया, हम जल्द संपर्क करेंगे।', 'Thank you! We received your message and will get back soon.')) ?></div><?php endif; ?>
      <?php foreach ($errors as $err): ?><div class="alert-err"><?= e($err) ?></div><?php endforeach; ?>
      <?= csrf_field() ?><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <label class="lbl"><?= e(tx('नाम', 'Name')) ?> *</label><input name="name" required maxlength="120" value="<?= e($f['name']) ?>">
      <div class="two"><div><label class="lbl">Email</label><input type="email" name="email" maxlength="150" value="<?= e($f['email']) ?>"></div>
        <div><label class="lbl"><?= e(tx('मोबाइल', 'Mobile')) ?></label><input name="mobile" inputmode="numeric" maxlength="14" value="<?= e($f['mobile']) ?>"></div></div>
      <label class="lbl"><?= e(tx('विषय', 'Subject')) ?></label><input name="subject" maxlength="200" value="<?= e($f['subject']) ?>">
      <label class="lbl"><?= e(tx('संदेश', 'Message')) ?> *</label><textarea name="message" rows="5" required maxlength="3000" style="width:100%;padding:11px;border:1px solid var(--line);border-radius:10px;font:15px var(--font-body)"><?= e($f['message']) ?></textarea>
      <button class="btn btn-amber" type="submit" style="margin-top:12px"><?= e(tx('संदेश भेजें', 'Send message')) ?></button>
    </form>
  </div>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
