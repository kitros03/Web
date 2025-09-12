document.addEventListener('DOMContentLoaded', function () {
  // BACK BUTTON
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.location.href = 'teacherdashboard.php';
    });
  }

  // Δυναμική διαχείριση πολλαπλών popup
  document.querySelectorAll('.popupBtn').forEach(button => {
    button.addEventListener('click', () => {
      const popup = button.nextElementSibling;
      if (popup && popup.classList.contains('popupWindow')) {
        popup.style.display = 'flex';
      }
    });
  });

  document.querySelectorAll('.closePopupBtn').forEach(button => {
    button.addEventListener('click', () => {
      const popup = button.closest('.popupWindow');
      if (popup) {
        popup.style.display = 'none';
      }
    });
  });

  // Κλείσιμο popup με κλικ έξω από το περιεχόμενο
  document.querySelectorAll('.popupWindow').forEach(popup => {
    popup.addEventListener('click', (e) => {
      if (e.target === popup) {
        popup.style.display = 'none';
      }
    });
  });

  // Διαχείριση κουμπιών με κλάση open-btn που ανακατευθύνουν
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.open-btn');
    if (!btn) return;

    const thesisID = btn.dataset.thesisId;
    const thStatus = btn.dataset.thStatus;

    if (!thesisID || !thStatus) {
      console.error('Missing data-thesis-id or data-th-status on the clicked button.');
      return;
    }

    let targetUrl;
    if (thStatus === 'ASSIGNED') {
      targetUrl = `assignedthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
    } else if (thStatus === 'ACTIVE') {
      targetUrl = `activethesis.php?thesisID=${encodeURIComponent(thesisID)}`;
    } else if (thStatus === 'EXAM') {
      targetUrl = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
    } else {
      targetUrl = `viewthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
    }

    window.location.href = targetUrl;
  });
});
