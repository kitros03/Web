document.addEventListener('DOMContentLoaded', () => {
    const backBtn = document.getElementById('backBtn');
    const tableBody = document.querySelector('#thesesTable tbody');
    const noThesesMsg = document.getElementById('noThesesMsg');

    if (backBtn) {
        backBtn.addEventListener('click', () => {
            window.location.href = 'teacherdashboard.php';
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");
    }

    function createChangesHtml(changes) {
        if (!changes || changes.length === 0) return '<p>No changes recorded for this thesis.</p>';
        return '<ul class="changes-list">' + changes.map(c =>
            `<li><strong>${escapeHtml(c.changeDate)} ${escapeHtml(c.changeTo)}</strong></li>`).join('') + '</ul>';
    }

    function createRow(thesis) {
        const changesHtml = createChangesHtml(thesis.changes);
        return `<tr>
                <td>${thesis.thesisID}</td>
                <td>${escapeHtml(thesis.title)}</td>
                <td>${escapeHtml(thesis.role)}</td>
                <td>${escapeHtml(thesis.th_status)}</td>
                <td>${escapeHtml(thesis.supervisorName)}</td>
                <td>${escapeHtml(thesis.member1Name || 'No first member in committee.')}</td>
                <td>${escapeHtml(thesis.member2Name || 'No second member in committee.')}</td>
                <td>${escapeHtml(thesis.grade || '')}</td>
                <td>
                    <button class="popupBtn">View Changes</button>
                    <div class="popupWindow popup-window" style="display:none;">
                        <div class="popup-content">
                            <h3>Changes Timeline</h3>
                            ${changesHtml}
                            <button class="closePopupBtn close-popup-btn" aria-label="Close">&times;</button>
                        </div>
                    </div>
                </td>
                <td>
                    ${(thesis.th_status === 'ASSIGNED' || thesis.th_status === 'ACTIVE' || thesis.th_status === 'EXAM')
                        ? `<button class="submit-btn open-btn" data-thesis-id="${thesis.thesisID}" 
                            data-th-status="${thesis.th_status}">Open</button>` 
                        : `<span>N/A</span>`}
                </td>
        </tr>`;
    }

    async function loadTheses() {
        try {
            const response = await fetch('viewtheses.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (!data.success || !data.theses.length) {
                noThesesMsg.style.display = 'block';
                tableBody.innerHTML = '';
                return;
            }
            noThesesMsg.style.display = 'none';
            tableBody.innerHTML = '';

            data.theses.forEach(thesis => {
                const tr = document.createElement('tr');
                tr.innerHTML = createRow(thesis);
                tableBody.appendChild(tr);
            });

            addPopupListeners();
            addOpenButtonListeners();
        } catch (error) {
            noThesesMsg.style.display = 'block';
            noThesesMsg.textContent = 'Error loading theses.';
            console.error(error);
        }
    }

    function addPopupListeners() {
        document.querySelectorAll('.popupBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const popup = btn.nextElementSibling;
                if (popup && popup.classList.contains('popupWindow')) {
                    popup.style.display = 'flex';
                }
            });
        });

        document.querySelectorAll('.closePopupBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const popup = btn.closest('.popupWindow');
                if (popup) popup.style.display = 'none';
            });
        });

        document.querySelectorAll('.popupWindow').forEach(popup => {
            popup.addEventListener('click', e => {
                if (e.target === popup) popup.style.display = 'none';
            });
        });
    }

    function addOpenButtonListeners() {
        document.addEventListener('click', e => {
            const btn = e.target.closest('.open-btn');
            if (!btn) return;

            const thesisID = btn.dataset.thesisId;
            const thStatus = btn.dataset.thStatus;

            if (!thesisID || !thStatus) {
                console.error('Missing data-thesis-id or data-th-status');
                return;
            }

            let targetUrl;
            if (thStatus === 'ASSIGNED')
                targetUrl = `assignedthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
            else if (thStatus === 'ACTIVE')
                targetUrl = `activethesis.php?thesisID=${encodeURIComponent(thesisID)}`;
            else if (thStatus === 'EXAM')
                targetUrl = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
            else
                targetUrl = `viewthesis.php?thesisID=${encodeURIComponent(thesisID)}`;

            window.location.href = targetUrl;
        });
    }

    loadTheses();
});
