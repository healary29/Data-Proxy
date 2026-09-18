<?php
$pageTitle = 'Track Order';
$hasBottomNav = false;
require __DIR__ . '/_header.php';
?>

<h2>Track / Report an Order</h2>
<p class="text-muted">Enter the phone number and order reference you used at checkout.</p>

<div id="alert" class="alert hidden"></div>

<form id="lookupForm">
  <label for="guest_phone">Phone Number</label>
  <input type="tel" id="guest_phone" placeholder="07XXXXXXXX" required>

  <label for="order_ref">Order Reference</label>
  <input type="text" id="order_ref" placeholder="DH-XXXXX" required style="text-transform:uppercase;">

  <button type="submit" class="btn" id="submitBtn">Look Up Order</button>
</form>

<div id="resultBox"></div>

<script>
let currentOrder = null;

document.getElementById('lookupForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert('alert');
  document.getElementById('resultBox').innerHTML = '';

  const btn = document.getElementById('submitBtn');
  setLoading(btn, true, 'Looking up...');

  const guest_phone = document.getElementById('guest_phone').value;
  const order_ref = document.getElementById('order_ref').value;

  const res = await apiPost('/dataproxy/api/guest_order_lookup.php', { guest_phone, order_ref });

  setLoading(btn, false);

  if (!res.success) {
    showAlert('alert', res.error || 'Order not found');
    return;
  }

  currentOrder = { guest_phone, order_ref: res.order.order_ref };

  let ticketsHtml = '';
  if (res.existing_tickets.length) {
    ticketsHtml = '<h3>Your Support Tickets</h3><div class="card">' +
      res.existing_tickets.map(t =>
        `<div class="list-item">
          <div><strong>${t.subject}</strong><br><span class="meta">${new Date(t.created_at).toLocaleDateString()}</span></div>
          <a href="/dataproxy/public/ticket_view.php?id=${t.id}&guest_phone=${encodeURIComponent(guest_phone)}&guest_order_ref=${encodeURIComponent(res.order.order_ref)}" class="badge ${t.status}">${t.status}</a>
        </div>`
      ).join('') + '</div>';
  }

  document.getElementById('resultBox').innerHTML = `
    <div class="card">
      <h3 class="mt-0">${res.order.bundle_name}</h3>
      <p class="text-muted">Recipient: ${res.order.recipient_phone}</p>
      <span class="badge ${res.order.status}">${res.order.status}</span>
    </div>
    ${ticketsHtml}
    <button class="btn secondary" id="raiseTicketBtn">Raise a New Complaint</button>
    <div id="ticketForm" class="card hidden" style="margin-top:14px;">
      <label for="subject">Subject</label>
      <input type="text" id="subject" placeholder="e.g. Bundle not received">
      <label for="message">Describe the issue</label>
      <textarea id="message" placeholder="What happened?"></textarea>
      <div id="ticketAlert" class="alert hidden"></div>
      <button class="btn" id="submitTicketBtn">Submit Complaint</button>
    </div>
  `;

  document.getElementById('raiseTicketBtn').addEventListener('click', () => {
    document.getElementById('ticketForm').classList.remove('hidden');
  });

  document.getElementById('submitTicketBtn').addEventListener('click', async () => {
    hideAlert('ticketAlert');
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;
    if (!subject || !message) {
      showAlert('ticketAlert', 'Please fill in both fields');
      return;
    }
    const tbtn = document.getElementById('submitTicketBtn');
    setLoading(tbtn, true, 'Submitting...');

    const tres = await apiPost('/dataproxy/api/raise_ticket.php', {
      order_type: 'data',
      subject, message,
      guest_phone: currentOrder.guest_phone,
      guest_order_ref: currentOrder.order_ref,
    });

    setLoading(tbtn, false);

    if (tres.success) {
      window.location.href = `/dataproxy/public/ticket_view.php?id=${tres.ticket_id}&guest_phone=${encodeURIComponent(currentOrder.guest_phone)}&guest_order_ref=${encodeURIComponent(currentOrder.order_ref)}`;
    } else {
      showAlert('ticketAlert', tres.error || 'Could not submit complaint');
    }
  });
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
