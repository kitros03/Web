document.getElementById('backBtn').onclick = () => {
  window.location.href = 'teacherdashboard.php';
};

document.querySelectorAll('.submit-btn').forEach(btn => {

  const popupBtn = document.getElementById('popupBtn');
  const popupWindow = document.getElementById('popupWindow');
  const closePopupBtn = document.getElementById('closePopupBtn');

  if (popupBtn && popupWindow && closePopupBtn) {
    popupBtn.onclick = function() {

      popupWindow.style.display = 'flex';
    };

    closePopupBtn.onclick = function() {
      popupWindow.style.display = 'none';
    };

    popupWindow.addEventListener('click', function(e) {
      if (e.target === popupWindow) {
        popupWindow.style.display = 'none';
      }
    });
  };

  btn.addEventListener('click', function() {
    const thesisID = this.dataset.thesisId;
    if(th_status === 'ASSIGNED'){
      window.location.href = `assignedthesis.php?thesisID=${thesisID}`;
    }
    else if(th_status === 'ACTIVE'){
      window.location.href = `activethesis.php?thesisID=${thesisID}`;
    }
    else if(th_status === 'EXAM'){
      window.location.href = `examthesis.php?thesisID=${thesisID}`;
    }
  });
});


