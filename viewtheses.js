document.addEventListener('DOMContentLoaded', function () {
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.location.href = 'teacherdashboard.php';
    });
  }

  const popupBtn = document.getElementById('popupBtn');
  const popupWindow = document.getElementById('popupWindow');
  const closePopupBtn = document.getElementById('closePopupBtn');

  if (popupBtn && popupWindow && closePopupBtn) {
    popupBtn.addEventListener('click', () => {
      popupWindow.style.display = 'flex';
    });
    closePopupBtn.addEventListener('click', () => {
      popupWindow.style.display = 'none';
    });
    popupWindow.addEventListener('click', (e) => {
      if (e.target === popupWindow) {
        popupWindow.style.display = 'none';
      }
    });
  }


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
