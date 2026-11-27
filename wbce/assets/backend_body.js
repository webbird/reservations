/* Reservations – backend: confirm before deleting or rejecting (forms with data-rsv-confirm), no inline handlers */
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches && form.matches('.mod_reservations form[data-rsv-confirm]') && !window.confirm(form.getAttribute('data-rsv-confirm'))) {
        e.preventDefault();
    }
});
