document.addEventListener('DOMContentLoaded', function() {
    const popup = document.getElementById('statsPopup');
    const closeBtn = document.getElementById('closePopupBtn');

    const timeCanvas = document.getElementById('popupCompletionTimeChart');
    const gradeCanvas = document.getElementById('popupAverageGradeChart');
    const countCanvas = document.getElementById('popupCountChart');

    function clearCharts() {
        [timeCanvas, gradeCanvas, countCanvas].forEach(c => {
            if (c.chart) {
                c.chart.destroy();
                c.chart = null;
            }
        });
    }

    function renderCharts(data) {
        clearCharts();

        timeCanvas.chart = new Chart(timeCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Επιβλέπων', 'Μέλος Τριμελούς'],
                datasets: [{
                    label: 'Μέσος Χρόνος Περάτωσης (ημέρες)',
                    data: [data.avgTimeSupervise || 0, data.avgTimeCommittee || 0],
                    backgroundColor: ['rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)']
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        gradeCanvas.chart = new Chart(gradeCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Επιβλέπων', 'Μέλος Τριμελούς'],
                datasets: [{
                    label: 'Μέσος Βαθμός',
                    data: [data.avgGradeSupervise || 0, data.avgGradeCommittee || 0],
                    backgroundColor: ['rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)']
                }]
            },
            options: { scales: { y: { beginAtZero: true, max: 10 } } }
        });

        countCanvas.chart = new Chart(countCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Επιβλέπων', 'Μέλος Τριμελούς'],
                datasets: [{
                    label: 'Συνολικό Πλήθος Διπλωματικών',
                    data: [data.countSupervise || 0, data.countCommittee || 0],
                    backgroundColor: ['rgba(255, 99, 132, 0.7)', 'rgba(255, 159, 64, 0.7)']
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    }

    function fetchDataAndShowPopup(type = 'both') {
        clearCharts();

        fetch('viewstats.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                if(data.error) {
                    alert('Σφάλμα: ' + data.error);
                    return;
                }

                if (
                    (type === 'supervise' && data.countSupervise === 0) ||
                    (type === 'committee' && data.countCommittee === 0) ||
                    (type === 'both' && data.countSupervise === 0 && data.countCommittee === 0)
                ) {
                    alert('Δεν υπάρχουν διαθέσιμες στατιστικές για την επιλογή σας.');
                    return;
                }

                renderCharts(data);
                popup.style.display = 'flex';
            })
            .catch(() => {
                alert('Σφάλμα κατά τη φόρτωση των στατιστικών.');
            });
    }

    document.getElementById('superviseStatsBtn').addEventListener('click', () => fetchDataAndShowPopup('supervise'));
    document.getElementById('committeeStatsBtn').addEventListener('click', () => fetchDataAndShowPopup('committee'));
    document.getElementById('bothStatsBtn').addEventListener('click', () => fetchDataAndShowPopup('both'));

    closeBtn.addEventListener('click', () => {
        popup.style.display = 'none';
        clearCharts();
    });

    popup.addEventListener('click', (e) => {
        if (e.target === popup) {
            popup.style.display = 'none';
            clearCharts();
        }
    });
});
