const container = document.getElementById('dashboard-container');

document.getElementById('thesisviewBtn').onclick = () => {
  window.location.href = 'thesisview.php';
};
document.getElementById('studentprofileBtn').onclick = () => {
  window.location.href = 'studentprofile.php';
};

document.getElementById('managethesesBtn').onclick = () => {
  window.location.href = 'viewtheses.php';
};

document.getElementById('logoutBtn').onclick = () => {
  window.location.href = 'logout.php';
};
