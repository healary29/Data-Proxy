<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();

$pageTitle = 'New Ticket';
$hasBottomNav = false;
require __DIR__ . '/_header.php';
?>

<h2>Raise a Support Ticket</h2>

<div id="alert" class="alert hidden"></div>

<form id="ticketForm">
  <label for="order_type">What is this about?</label>
  <select id="order_type" required>
    <option value="data">Data Bundle Order</option>
    <option value="proxy">Proxy Access</option>
    <option value="wallet">Wallet / Payment</option>
    <option value="other">Other</option>
  </select>

  <label for="subject">Subject</label>
  <input type="text" id="subject" placeholder="Short summary" required>

  <label for="message">Message</label>
  <textarea id="message" placeholder="Describe the issue in detail" required></textarea>

  <button type="submit" class="btn" id="submitBtn">Submit Ticket</button>
</form>

<script>
document.getElementById('ticketForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert('alert');
  const btn = document.getElementById('submitBtn');
  setLoading(btn, true, 'Submitting...');

  const res = await apiPost('/dataproxy/api/raise_ticket.php', {
    order_type: document.getElementById('order_type').value,
    subject: document.getElementById('subject').value,
    message: document.getElementById('message').value,
  });

  setLoading(btn, false);

  if (res.success) {
    window.location.href = '/dataproxy/public/ticket_view.php?id=' + res.ticket_id;
  } else {
    showAlert('alert', res.error || 'Could not submit ticket');
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
