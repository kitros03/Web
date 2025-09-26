document.addEventListener('DOMContentLoaded', function() {
    const popup = document.getElementById('statsPopup');
    const closeBtn = document.getElementById('closePopupBtn');

    const timeCanvas = document.getElementById('popupCompletionTimeChart');
    const gradeCanvas = document.getElementById('popupAverageGradeChart');
    const countCanvas = document.getElementById('popupCountChart');

    // Clear existing charts if any
    function clearCharts() {
        [timeCanvas, gradeCanvas, countCanvas].forEach(c => {
            if (c.chart) {
                c.chart.destroy();
                c.chart = null;
            }
        });
    }

    // Render charts with data filtered by type
    function renderCharts(data, type) {
        clearCharts();

        const labels = [];
        const timeData = [];
        const gradeData = [];
        const countData = [];
        const bgColors = [];

        if (type === 'supervise' || type === 'both') {
            labels.push('Επιβλέπων');
            timeData.push(data.avgTimeSupervise || 0);
            gradeData.push(data.avgGradeSupervise || 0);
            countData.push(data.countSupervise || 0);
            bgColors.push('rgba(54, 162, 235, 0.7)'); // Blue
        }
        if (type === 'committee' || type === 'both') {
            labels.push('Μέλος Τριμελούς');
            timeData.push(data.avgTimeCommittee || 0);
            gradeData.push(data.avgGradeCommittee || 0);
            countData.push(data.countCommittee || 0);
            bgColors.push('rgba(255, 206, 86, 0.7)'); // Yellow
        }

        timeCanvas.chart = new Chart(timeCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Μέσος Χρόνος Περάτωσης (ημέρες)',
                    data: timeData,
                    backgroundColor: bgColors
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        gradeCanvas.chart = new Chart(gradeCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Μέσος Βαθμός',
                    data: gradeData,
                    backgroundColor: bgColors
                }]
            },
            options: { scales: { y: { beginAtZero: true, max: 10 } } }
        });

        countCanvas.chart = new Chart(countCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Συνολικό Πλήθος Διπλωματικών',
                    data: countData,
                    backgroundColor: bgColors
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    }

    // Fetch data and show popup filtered by type
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

                renderCharts(data, type);
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
