<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/UserPush.php';
sa_require_login();

$pdo = admin_db();
UserPush::ensureTables($pdo);
UserPush::processDueCampaigns($pdo);

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $audience = trim((string) ($_POST['audience'] ?? 'all_users'));
    $targetPhone = preg_replace('/\D+/', '', (string) ($_POST['target_phone'] ?? '')) ?? '';
    if (strlen($targetPhone) === 12 && str_starts_with($targetPhone, '91')) {
        $targetPhone = substr($targetPhone, 2);
    }
    $sendMode = trim((string) ($_POST['send_mode'] ?? 'now'));
    $scheduledRaw = trim((string) ($_POST['scheduled_at'] ?? ''));

    if ($title === '' || $body === '') {
        $error = 'Title and body are required';
    } elseif ($audience === 'specific_user' && strlen($targetPhone) !== 10) {
        $error = 'Enter a valid 10-digit phone for a specific user';
    } else {
        try {
            $sendNow = $sendMode !== 'schedule';
            $scheduledAt = null;
            if (!$sendNow) {
                if ($scheduledRaw === '') {
                    throw new InvalidArgumentException('Schedule date/time required');
                }
                $ts = strtotime($scheduledRaw);
                if ($ts === false) {
                    throw new InvalidArgumentException('Invalid schedule time');
                }
                $scheduledAt = date('Y-m-d H:i:s', $ts);
            }
            $result = UserPush::createCampaign(
                $title,
                $body,
                $audience === 'specific_user' ? 'specific_user' : 'all_users',
                $audience === 'specific_user' ? $targetPhone : null,
                $scheduledAt,
                $sendNow,
                $pdo
            );
            if ($sendNow) {
                $flash = 'Campaign #' . $result['id'] . ' sent — delivered ' . $result['sent_count']
                    . ', failed ' . $result['fail_count'];
            } else {
                $flash = 'Campaign #' . $result['id'] . ' scheduled for ' . $scheduledAt;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$campaigns = $pdo->query(
    'SELECT * FROM user_notification_campaigns ORDER BY id DESC LIMIT 50'
)->fetchAll();

sa_layout_start('User notifications', 'user-notifications.php', 'Push campaigns to the customer app');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>

<div class="card max-w-2xl">
  <h3>Send push notification</h3>
  <form method="post" class="space-y-1">
    <label>Title</label>
    <input class="input" name="title" required maxlength="255" placeholder="Festival offer">

    <label>Body</label>
    <textarea class="input" name="body" required rows="3" maxlength="1000" placeholder="Order now and get free delivery"></textarea>

    <label>Audience</label>
    <select class="input" name="audience" id="audienceSelect" onchange="toggleTargetPhone()">
      <option value="all_users">All users</option>
      <option value="specific_user">Specific user (phone)</option>
    </select>

    <div id="targetPhoneWrap" class="hidden">
      <label>User phone</label>
      <input class="input" name="target_phone" type="tel" maxlength="15" placeholder="10-digit mobile">
    </div>

    <label>When</label>
    <select class="input" name="send_mode" id="sendMode" onchange="toggleSchedule()">
      <option value="now">Send now</option>
      <option value="schedule">Schedule</option>
    </select>

    <div id="scheduleWrap" class="hidden">
      <label>Schedule at</label>
      <input class="input" type="datetime-local" name="scheduled_at">
    </div>

    <button class="btn mt-2" type="submit">
      <span class="material-icons-outlined text-[18px]">campaign</span> Create campaign
    </button>
  </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mt-4">
  <div class="px-4 py-3 border-b border-gray-100">
    <h3 class="!mb-0 text-base font-bold text-gray-900">Recent campaigns</h3>
  </div>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Title</th><th>Audience</th><th>Status</th><th>Sent / Fail</th><th>Schedule</th><th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $c): ?>
          <tr>
            <td class="font-semibold text-primary">#<?= (int) $c['id'] ?></td>
            <td>
              <p class="font-medium text-gray-900"><?= sa_h($c['title']) ?></p>
              <p class="muted line-clamp-1"><?php
                $bodyPreview = (string) $c['body'];
                if (strlen($bodyPreview) > 80) {
                    $bodyPreview = substr($bodyPreview, 0, 77) . '…';
                }
                echo sa_h($bodyPreview);
              ?></p>
            </td>
            <td>
              <?= sa_h($c['audience']) ?>
              <?php if (!empty($c['target_phone'])): ?>
                <span class="muted block"><?= sa_h($c['target_phone']) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?= sa_h($c['status']) ?></span></td>
            <td><?= (int) $c['sent_count'] ?> / <?= (int) $c['fail_count'] ?></td>
            <td class="muted whitespace-nowrap"><?= sa_h((string) ($c['scheduled_at'] ?? '—')) ?></td>
            <td class="muted whitespace-nowrap"><?= sa_h($c['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$campaigns): ?>
          <tr><td colspan="7" class="text-center text-gray-500 py-10">No campaigns yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleTargetPhone(){
  var el = document.getElementById('targetPhoneWrap');
  var sel = document.getElementById('audienceSelect');
  if (!el || !sel) return;
  if (sel.value === 'specific_user') el.classList.remove('hidden');
  else el.classList.add('hidden');
}
function toggleSchedule(){
  var el = document.getElementById('scheduleWrap');
  var sel = document.getElementById('sendMode');
  if (!el || !sel) return;
  if (sel.value === 'schedule') el.classList.remove('hidden');
  else el.classList.add('hidden');
}
toggleTargetPhone();
toggleSchedule();
</script>
<?php sa_layout_end(); ?>
