
// secretary_view_theses.js
(function () {
  'use strict';

  var listDiv    = document.getElementById('thesesList');
  var detailsDiv = document.getElementById('thesisDetails');
  var searchBox  = document.getElementById('searchBox');

  var cachedList = [];
  var currentOpenId = null;

  function statusGR(s) {
    var map = {
      'NOT_ASSIGNED': 'Μη Ανατεθειμένη',
      'ASSIGNED':     'Ανατεθειμένη',
      'ACTIVE':       'Ενεργή',
      'EXAM':         'Υπό Εξέταση',
      'DONE':         'Περατωμένη',
      'CANCELLED':    'Ακυρωμένη'
    };
    return map[s] || s || '';
  }

  function daysLabel(days) {
    if (days === null || days === undefined) return '—';
    var d = Number(days);
    if (isNaN(d)) return '—';
    if (d === 0) return '0 ημέρες';
    if (d === 1) return '1 ημέρα';
    return String(d) + ' ημέρες';
  }

  function hideDetails() {
    if (detailsDiv) {
      detailsDiv.innerHTML = '';
      detailsDiv.style.display = 'none';
    }
    currentOpenId = null;
  }

  function renderList(items) {
    if (!listDiv) return;

    if (currentOpenId && !items.some(function (x) { return String(x.thesisID) === String(currentOpenId); })) {
      hideDetails();
    }
    if (!items || !items.length) {
      listDiv.innerHTML = '<p>Δεν βρέθηκαν διπλωματικές.</p>';
      return;
    }

    var rows = [];
    rows.push('<table class="table"><thead><tr>');
    rows.push('<th>Κωδ.</th><th>Τίτλος</th><th>Κατάσταση</th><th>Επιβλέπων</th><th>Φοιτητής</th><th>Από ανάθεση</th><th>Λεπτομέρειες</th>');
    rows.push('</tr></thead><tbody>');

    items.forEach(function (it) {
      rows.push('<tr>');
      rows.push('<td>' + (it.thesisID != null ? it.thesisID : '') + '</td>');
      rows.push('<td>' + (it.title || '') + '</td>');
      rows.push('<td>' + statusGR(it.th_status) + '</td>');
      rows.push('<td>' + (it.supervisor_name || '—') + '</td>');
      rows.push('<td>' + (it.student_name || '—') + '</td>');
      rows.push('<td>' + daysLabel(it.days_since_assignment) + '</td>');
      // ΜΟΝΟ “Λεπτομέρειες”
      rows.push('<td><button class="sidebarButton btn-details" data-id="' + it.thesisID + '">Λεπτομέρειες</button></td>');
      rows.push('</tr>');
    });

    rows.push('</tbody></table>');
    listDiv.innerHTML = rows.join('');
  }

  function fetchJSON(url, options) {
    options = options || {};
    if (!options.credentials) options.credentials = 'same-origin';

    return fetch(url, options).then(function (res) {
      return res.text().then(function (text) {
        var data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          var preview = (text || '').slice(0, 400);
          throw new Error('Μη έγκυρη JSON απόκριση από ' + url + '. Πρώτα bytes:\n' + preview);
        }
        if (!res.ok) {
          var msg = (data && (data.message || data.error)) ? (data.message || data.error) : (res.status + ' ' + (res.statusText || ''));
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function loadList() {
    if (listDiv) listDiv.innerHTML = '<p>Φόρτωση…</p>';
    fetchJSON('secretary_theses_list.php')
      .then(function (data) {
        cachedList = Array.isArray(data) ? data : [];
        renderList(cachedList);
      })
      .catch(function (e) {
        if (listDiv) listDiv.innerHTML = '<p class="error">' + e.message + '</p>';
        if (window.console && console.error) console.error(e);
      });
  }

  function filtered() {
    var q = '';
    if (searchBox && typeof searchBox.value === 'string') {
      q = searchBox.value.toLowerCase().trim();
    }
    if (!q) return cachedList.slice();

    return cachedList.filter(function (x) {
      var t1 = (x.title || '').toLowerCase();
      var t2 = (x.supervisor_name || '').toLowerCase();
      var t3 = (x.student_name || '').toLowerCase();
      var t4 = String(x.thesisID || '').toLowerCase();
      return t1.indexOf(q) !== -1 || t2.indexOf(q) !== -1 || t3.indexOf(q) !== -1 || t4.indexOf(q) !== -1;
    });
  }

  function buildDetailsHTML(d) {
    var committee = (d && d.committee) ? d.committee : [];
    var committeeList = (committee && committee.length) ? committee.join(', ') : '—';
    return '' +
      '<h3>Λεπτομέρειες ΔΕ #' + d.thesisID + '</h3>' +
      '<p><strong>Τίτλος:</strong> ' + (d.title || '') + '</p>' +
      '<p><strong>Καθηγητής-Επιβλέπων:</strong> ' + (d.supervisor_name || '—') + '</p>' +
      '<p><strong>Επιτροπή:</strong> ' + committeeList + '</p>' +
      '<p><strong>Στάδιο:</strong> ' + statusGR(d.th_status) + '</p>' +
      '<p><strong>Χρόνος από επίσημη ανάθεση:</strong> ' + (d.days_since_assignment != null ? d.days_since_assignment : '—') + '</p>' +
      '<p><strong>Περιγραφή:</strong><br>' + (d.th_description || '') + '</p>';
  }

  document.addEventListener('click', function (e) {
    var target = e.target || e.srcElement;

    var btnDetails = target && target.closest ? target.closest('button.btn-details[data-id]') : null;
    if (btnDetails) {
      var thesisID = btnDetails.getAttribute('data-id');
      if (currentOpenId && String(currentOpenId) === String(thesisID)) { hideDetails(); return; }
      currentOpenId = thesisID;
      if (detailsDiv) {
        detailsDiv.style.display = 'block';
        detailsDiv.innerHTML = '<p>Φόρτωση…</p>';
      }
      fetchJSON('secretary_thesis_details.php?thesisID=' + encodeURIComponent(thesisID))
        .then(function (d) {
          if (detailsDiv) detailsDiv.innerHTML = buildDetailsHTML(d);
        })
        .catch(function (err) {
          if (detailsDiv) detailsDiv.innerHTML = '<p class="error">' + err.message + '</p>';
          if (window.console && console.error) console.error(err);
        });
      return;
    }
  });

  if (searchBox) {
    searchBox.addEventListener('input', function () {
      renderList(filtered());
    });
  }

  // αρχικό load
  loadList();
})();
