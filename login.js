// Helper to get URL parameter
function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const role = getQueryParam('role'); // Get the role from the URL

    const response = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ username, password, role })
    });

    const result = await response.json();
    if (result.success) {
        window.location.href = result.dashboard;
    } else {
        document.getElementById('loginError').textContent = result.message;
    }
});
