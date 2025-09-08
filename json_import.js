document.getElementById("studentassignmentBtn").addEventListener("click", () => {
    let input = document.createElement("input");
    input.type = "file";
    input.accept = ".json";

    input.onchange = e => {
        let file = e.target.files[0];
        if (!file) return;

        let reader = new FileReader();
        reader.onload = function(evt) {
            try {
                let jsonData = JSON.parse(evt.target.result);

                fetch("json_import.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(jsonData)
                })
                .then(res => res.json())
                .then(response => {
                    alert(response.message);
                })
                .catch(err => {
                    console.error("Σφάλμα AJAX:", err);
                    alert("Αποτυχία αποστολής στο server");
                });
            } catch (err) {
                alert("Το αρχείο δεν είναι έγκυρο JSON");
                console.error("JSON Error:", err);
            }
        };
        reader.readAsText(file);
    };

    input.click();
});
