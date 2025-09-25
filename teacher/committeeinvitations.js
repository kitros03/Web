document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('invitationBody');
  const backBtn = document.getElementById('backBtn');

    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });
  
  //load data
  async function loadInvitations() {
    try {
      const response = await fetch('committeeinvitations.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) throw new Error('Network response was not ok');

      const data = await response.json();

      if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="4">Δεν βρέθηκαν προσκλήσεις.</td></tr>';
        return;
      }

      //show table content
      const rows = data.map(inv => {
        return `
          <tr data-id="${inv.invitationID}">
            <td>${inv.thesisTitle || '–'}</td>
            <td>${inv.senderName || '–'}</td>
            <td>${inv.invitationDate}</td>
            <td>
              <button class="accept-btn" data-id="${inv.invitationID}">Αποδοχή</button>
              <button class="reject-btn" data-id="${inv.invitationID}">Απόρριψη</button>
            </td>
          </tr>`;
      }).join('');

      tbody.innerHTML = rows;

      //handle accept/reject
      tbody.querySelectorAll('.accept-btn, .reject-btn').forEach(button => {
        button.onclick = async () => {
          const decision = button.classList.contains('accept-btn') ? 'accept' : 'reject';
          if (!confirm(`Θέλετε να ${decision === 'accept' ? 'αποδεχτείτε' : 'απορρίψετε'} αυτή την πρόσκληση;`)) return;
          const invID = button.dataset.id;
          const formData = new FormData();
          formData.append('invitationID', invID);
          formData.append('decision', decision);

          try {
            const resp = await fetch('committeeinvitations.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (!result.success) {
              alert(result.message || 'Σφάλμα στην ενημέρωση.');
              return;
            }
            alert(`Η πρόσκληση ${decision === 'accept' ? 'αποδεκτή' : 'απορρίφθηκε'}.`);
            button.closest('tr').remove();

            if (tbody.children.length === 0) {
              tbody.innerHTML = '<tr><td colspan="4">Δεν βρέθηκαν προσκλήσεις.</td></tr>';
            }
          } catch (err) {
            alert('Προέκυψε σφάλμα δικτύου.');
          }
        };
      });
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="4">Προβλήθηκε σφάλμα στην φόρτωση.</td></tr>';
      console.error(error);
    }
  }

  tbody.innerHTML = '<tr><td colspan="4">Φόρτωση...</td></tr>';
  loadInvitations();
});
