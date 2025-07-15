const container = document.getElementById('div');

document.body.appendChild(container);

document.getElementById('thesiscreationBtn').onclick = () => {
  window.location.href = 'thesiscreation.php';
};
document.getElementById('studentassignmentBtn').onclick = () => {
  window.location.href = 'studentassignment.php';
};
document.getElementById('committeeinvitationsBtn').onclick = () => {
  window.location.href = 'committeeinvitations.php';
};
document.getElementById('viewstatsBtn').onclick = () => {
  window.location.href = 'viewstats.php';
};
document.getElementById('managethesesBtn').onclick = () => {
  window.location.href = 'managetheses.php';
};

