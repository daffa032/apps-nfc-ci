window.addEventListener('DOMContentLoaded', event => {
    // Simple-DataTables
    // https://github.com/fiduswriter/Simple-DataTables/wiki

    const datatablesSimple = document.getElementById('datatablesSimple');
    if (datatablesSimple && window.simpleDatatables) {
        new simpleDatatables.DataTable(datatablesSimple);
    }
});
