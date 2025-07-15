const container = document.createElement('div');

document.body.appendChild(container);

document.getElementById('teacherBtn').onclick = () => {
  window.location.href = 'login.html?role=teacher';
};
document.getElementById('studentBtn').onclick = () => {
  window.location.href = 'login.html?role=student';
};
document.getElementById('secretaryBtn').onclick = () => {
  window.location.href = 'login.html?role=secretary';
};
