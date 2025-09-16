// Αυτόματο focus στο πρώτο κενό πεδίο για εκτύπωση
document.addEventListener('DOMContentLoaded', function() {
    console.log('Πρακτικό εξέτασης φορτώθηκε');
});

// Print function
function printDocument() {
    window.print();
}

// Keyboard shortcut για εκτύπωση (Ctrl+P)
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printDocument();
    }
});

// Auto-print option (optional)
window.addEventListener('load', function() {
    // Uncomment if you want auto-print on load
    // setTimeout(() => window.print(), 500);
});
