(function () {
  const dotted = (v, f='………………………………') => {
    const s = (v ?? '').toString().trim();
    return s !== '' ? s : f;
  };

  function setText(sel, val, fallback='………………………………') {
    const el = document.querySelector(sel);
    if (el) el.textContent = dotted(val, fallback);
  }

  function fillListOrdered(listSelector, members, padTo=3) {
    const ol = document.querySelector(listSelector);
    if (!ol) return;
    ol.innerHTML = '';
    (members || []).forEach(m => {
      const li = document.createElement('li');
      li.textContent = dotted(m?.full) + ',';
      ol.appendChild(li);
    });
    for (let i = (members||[]).length; i < padTo; i++) {
      const li = document.createElement('li');
      li.textContent = '………………………………………………………………………………,';
      ol.appendChild(li);
    }
  }

  function fillSignatureTable(tableSelector, members, padTo=3) {
    const tbody = document.querySelector(`${tableSelector} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    (members || []).forEach(m => {
      const tr = document.createElement('tr');
      const td1 = document.createElement('td');
      const td2 = document.createElement('td');
      td1.textContent = dotted(m?.full);
      td2.textContent = dotted(m?.role);
      tr.appendChild(td1);
      tr.appendChild(td2);
      tbody.appendChild(tr);
    });
    for (let i = (members||[]).length; i < padTo; i++) {
      const tr = document.createElement('tr');
      tr.appendChild(document.createElement('td'));
      tr.appendChild(document.createElement('td'));
      tbody.appendChild(tr);
    }
  }

  async function fetchData(thesisID) {
    const url = `praktiko_form_fetch.php?thesisID=${encodeURIComponent(thesisID)}`;
    let res;
    try {
      res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    } catch {
      throw new Error('Δεν ήταν δυνατή η επικοινωνία με τον server.');
    }
    let payload = null;
    try { payload = await res.json(); } catch {}
    if (!res.ok) {
      const msg = payload?.error || `Σφάλμα φόρτωσης (${res.status})`;
      throw new Error(msg);
    }
    return payload;
  }

  function render(d) {
    // Επικεφαλίδα και στοιχεία εξέτασης
    setText('#studentName', `Κ. ${dotted(d.studentFull)}`, `Κ. ${'………………………………'}`);
    setText('#examRoom', d.room, '……………………………');
    setText('#examDate', d.dateStr, '……………………………');
    setText('#examDay', d.dayDate, '……');
    setText('#examTime', d.timeStr, '………………');
    setText('#gsText', d.gsText, '…………………');

    // Ονόματα και τίτλος σε όλα τα σημεία που επαναλαμβάνονται
    setText('#studentNameInline', d.studentFull);
    setText('#thesisTitle', d.title);
    setText('#supervisorFull', d.supervisorFull);
    setText('#studentNameInline2', d.studentFull);

    // Λίστα «Φυσική σειρά»
    fillListOrdered('#membersOriginalList', d.membersOriginal || [], 3);

    // Λίστα «Αλφαβητική σειρά»
    fillListOrdered('#membersAlphaList', d.membersAlpha || [], 3);

    // Κείμενα που ακολουθούν
    setText('#studentNameInline3', d.studentFull);
    setText('#supervisorFull2', d.supervisorFull);
    setText('#studentNameInline4', d.studentFull);
    setText('#finalGradeText', d.finalGradeText, '………………');

    // Πίνακας υπογραφών από αλφαβητική σειρά
    fillSignatureTable('#signatureTable', d.membersAlpha || [], 3);

    // Τελευταίες επαναλήψεις
    setText('#finalGradeText2', d.finalGradeText, '………………');
    setText('#studentNameInline5', d.studentFull);
  }

  function printDocument() { window.print(); }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const thesisID = window.__PRAKTIKO__?.thesisID;
      if (!thesisID) throw new Error('Λείπει thesisID');
      const data = await fetchData(thesisID);
      if (data?.error) throw new Error(data.error);
      render(data);
    } catch (err) {
      console.error(err);
      alert('Αποτυχία φόρτωσης δεδομένων: ' + err.message);
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
      e.preventDefault();
      printDocument();
    }
  });
})();
