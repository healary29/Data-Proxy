<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$ticketId = (int) ($_GET['id'] ?? 0);
$guestPhone = $_GET['guest_phone'] ?? '';
$guestOrderRef = $_GET['guest_order_ref'] ?? '';

$pageTitle = 'Ticket #' . $ticketId;
$hasBottomNav = (bool) $user;
require __DIR__ . '/_header.php';
?>

<h2>Ticket #<?= $ticketId ?></h2>

<div id="alert" class="alert hidden"></div>
<div id="threadBox"></div>

<div class="card" style="margin-top:16px;">
  <label for="replyMessage">Reply</label>
  <textarea id="replyMessage" placeholder="Type your message..."></textarea>
  <button class="btn" id="replyBtn">Send Reply</button>
</div>

<script>
const ticketId = <?= json_encode($ticketId) ?>;
const guestPhone = <?= json_encode($guestPhone) ?>;
const guestOrderRef = <?= json_encode($guestOrderRef) ?>;

function qs(params) {
  return Object.entries(params).filter(([,v]) => v).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&');
}

async function loadThread() {
  hideAlert('alert');
  const res = await apiGet('/dataproxy/api/ticket_reply.php?' + qs({ ticket_id: ticketId, guest_phone: guestPhone, guest_order_ref: guestOrderRef }));

  if (!res.success) {
    showAlert('alert', res.error || 'Could not load ticket');
    return;
  }

  const badgeClass = res.ticket.status;
  let html = `<div class="card">
    <strong>${res.ticket.subject}</strong>
    <span class="badge ${badgeClass}" style="float:right;">${res.ticket.status.replace('_',' ')}</span>
  </div>`;

  html += '<div class="card">';
  res.replies.forEach(r => {
    const who = r.sender === 'admin' ? 'Support Team' : 'You';
    html += `<div style="margin-bottom:12px;">
      <strong>${who}</strong> <span class="meta">${new Date(r.created_at).toLocaleString()}</span>
      <p style="margin:4px 0 0;">${r.message.replace(/</g,'&lt;')}</p>
    </div>`;
  });
  html += '</div>';

  document.getElementById('threadBox').innerHTML = html;
}

document.getElementById('replyBtn').addEventListener('click', async () => {
  hideAlert('alert');
  const message = document.getElementById('replyMessage').value.trim();
  if (!message) return;

  const btn = document.getElementById('replyBtn');
  setLoading(btn, true, 'Sending...');

  const res = await apiPost('/dataproxy/api/ticket_reply.php', {
    ticket_id: ticketId, message, guest_phone: guestPhone, guest_order_ref: guestOrderRef,
  });

  setLoading(btn, false);

  if (res.success) {
    document.getElementById('replyMessage').value = '';
    loadThread();
  } else {
    showAlert('alert', res.error || 'Could not send reply');
  }
});

loadThread();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
