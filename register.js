document.getElementById('backBtn').onclick = () => {
    window.location.href = 'index.php';
}
document.getElementById('registerForm').addEventListener('submit', async function (e){
    e.preventDefault();
    const id = document.getElementById('id').value;
    const fname = document.getElementById('fname').value;
    const lname = document.getElementById('lname').value;
    const username = document.getElementById('username').value;
    const pass = document.getElementById('password').value;
    const r_pass = document.getElementById('r-pass').value;
    const role = document.getElementById('role').value;

    const response = await fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({id, fname, lname, username, pass, r_pass, role})
    });

    const result = await response.json();
    const messageElement = document.getElementById('result');
    if (result.success) {
        messageElement.innerHTML = `<div class="success">${result.message}</div>`;
        setTimeout(() => window.location.href ='index.php', 1000);
    } else {
        messageElement.innerHTML = `<div class="error">${result.message}</div>`;
    }
});