
document.addEventListener('DOMContentLoaded', function() {
  fetch('thesis_details.php')
    .then(response => response.json())
    .then(data => {
      document.getElementById('thesis-details').innerHTML = `
        <ul>
          <li><strong>Θέμα:</strong> ${data.title}</li>
          <li><strong>Περιγραφή:</strong> ${data.desc}</li>
          <li><strong>Συνημμένο αρχείο:</strong> 
            <a href="${data.file}" target="_blank">Προβολή ή Λήψη</a>
          </li>
          <li><strong>Κατάσταση:</strong> ${data.status}</li>
          <li><strong>Επιτροπή:</strong>
            <ul>
              ${(data.committee || []).map(m => `<li>${m}</li>`).join('')}
            </ul>
          </li>
          <li><strong>Χρόνος από ανάθεση:</strong> ${data.days_since_assignment}</li>
        </ul>
      `;
    });
});
