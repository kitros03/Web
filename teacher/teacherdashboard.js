document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('thesiscreationBtn').onclick = () => location.href = 'thesiscreation.php';
    document.getElementById('studentassignmentBtn').onclick = () => location.href = 'studentassignment.php';
    document.getElementById('committeeinvitationsBtn').onclick = () => location.href = 'committeeinvitations.php';
    document.getElementById('viewstatsBtn').onclick = () => location.href = 'viewstats.php';
    document.getElementById('managethesesBtn').onclick = () => location.href = 'viewtheses.php';
    document.getElementById('logoutBtn').onclick = () => location.href = '../logout.php';

    // fortosi anakoinoseon
    const announcementsSection = document.getElementById('announcementsSection');
    async function loadAnnouncements() {
        try {
            const response = await fetch('../announcements.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!data.success || !data.announcements.length) {
                announcementsSection.innerHTML = '<p>Δεν υπάρχουν ανακοινώσεις αυτή τη στιγμή.</p>';
                return;
            }

            let html = '<h2>Ανακοινώσεις</h2><ul class="announcement-list">';
            data.announcements.forEach(announcement => {
                html += `<li class="announcement-item">`;
                html += `<h3>Ανακοίνωση Παρουσίασης</h3>`;
                html += `<p><strong>Ημερομηνία & Ώρα:</strong> ${new Date(announcement.exam_datetime).toLocaleString('el-GR')}</p>`;
                if (announcement.exam_meeting_url) {
                    html += `<p><strong>Σύνδεσμος Συνάντησης:</strong> <a href="${announcement.exam_meeting_url}" target="_blank">${announcement.exam_meeting_url}</a></p>`;
                } else if (announcement.exam_room) {
                    html += `<p><strong>Αίθουσα:</strong> ${announcement.exam_room}</p>`;
                }
                html += `</li>`;
            });
            html += '</ul>';
            announcementsSection.innerHTML = html;
        } catch (e) {
            announcementsSection.innerHTML = '<p>Σφάλμα φόρτωσης ανακοινώσεων.</p>';
            console.error(e);
        }
    }

    loadAnnouncements();
});
