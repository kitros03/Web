document.getElementById('backBtn').onclick = () => {
  window.location.href = 'teacherdashboard.php';
};

document.querySelectorAll('.submit-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const thesisID = this.dataset.thesisId;
    window.location.href = 'managethesis.php?id=' + encodeURIComponent(thesisID);
  });
});


