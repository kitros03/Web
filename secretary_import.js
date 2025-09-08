// secretary_import.js
(function () {
  const fileInput = document.getElementById('jsonFile');
  const uploadBtn = document.getElementById('uploadBtn');
  const resultBox = document.getElementById('result');

  function showResult(html) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = html;
  }

  uploadBtn?.addEventListener('click', () => {
    const file = fileInput?.files?.[0];
    if (!file) {
      showResult('<div class="error">Παρακαλώ επιλέξτε αρχείο JSON.</div>');
      return;
    }

    const reader = new FileReader();
    reader.onload = async (evt) => {
      try {
        const payload = JSON.parse(evt.target.result);

        const res = await fetch('json_import.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (!data || typeof data.success === 'undefined') {
          showResult('<div class="error">Μη αναμενόμενη απόκριση από τον server.</div>');
          return;
        }

        const rows = [];
        rows.push(`<p><strong>Αποτέλεσμα:</strong> ${data.success ? '✅ Επιτυχία' : '❌ Αποτυχία'}</p>`);
        if (data.summary) {
          rows.push('<table class="table">');
          rows.push('<tr><th>Κατηγορία</th><th>Επιτυχείς</th><th>Ενημερωμένες</th><th>Απέτυχαν</th></tr>');
          rows.push(`<tr><td>Φοιτητές</td><td>${data.summary.students?.inserted ?? 0}</td><td>${data.summary.students?.updated ?? 0}</td><td>${data.summary.students?.failed ?? 0}</td></tr>`);
          rows.push(`<tr><td>Διδάσκοντες</td><td>${data.summary.teachers?.inserted ?? 0}</td><td>${data.summary.teachers?.updated ?? 0}</td><td>${data.summary.teachers?.failed ?? 0}</td></tr>`);
          rows.push('</table>');
        }
        if (Array.isArray(data.errors) && data.errors.length) {
          rows.push('<h4>Σφάλματα</h4><ul>');
          data.errors.forEach(e => rows.push(`<li>${e}</li>`));
          rows.push('</ul>');
        }
        showResult(rows.join(''));
      } catch (err) {
        console.error(err);
        showResult('<div class="error">Το αρχείο δεν είναι έγκυρο JSON ή προέκυψε σφάλμα κατά την αποστολή.</div>');
      }
    };
    reader.readAsText(file);
  });
})();
