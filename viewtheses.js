document.getElementById('backBtn').onclick = () => {
  window.location.href = 'teacherdashboard.php';
};

document.querySelectorAll('select-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const thesisID = this.dataset.thesisId;
    window.location.href = 'managethesis.php?thesisID=' + encodeURIComponent(thesisID);
  });
});

