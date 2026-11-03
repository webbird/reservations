/*
 * Reservations – frontend_body.js
 * With a choice of objects in the request form: shows the limits of the chosen
 * object and sets them on the fields (dates, number of persons). Without
 * JavaScript all limit lines stay visible and request.php checks anyway.
 */
document.querySelectorAll('.rsv-request select[name="resource_id"]').forEach(function (select) {
    var form = select.form;
    var limits = document.getElementById(select.getAttribute('data-limits'));
    var set = function (field, name, value) {
        if (!field) {
            return;
        }
        if (value) {
            field.setAttribute(name, value);
        } else {
            field.removeAttribute(name);
        }
    };
    var apply = function () {
        var data = select.options[select.selectedIndex].dataset;
        ['start', 'end'].forEach(function (name) {
            set(form.elements[name], 'min', data.min);
            set(form.elements[name], 'step', data.step);
        });
        set(form.elements.start, 'max', data.max);
        set(form.elements.persons, 'min', data.personsMin);
        set(form.elements.persons, 'max', data.personsMax);
        if (form.elements.persons) {
            form.elements.persons.required = data.personsRequired === '1';
            var mark = form.elements.persons.labels[0] && form.elements.persons.labels[0].querySelector('.rsv-required-mark');
            if (mark) {
                mark.hidden = !form.elements.persons.required;
            }
        }
        if (limits) {
            limits.querySelectorAll('[data-resource]').forEach(function (line) {
                line.hidden = line.getAttribute('data-resource') !== select.value;
            });
        }
    };
    select.addEventListener('change', apply);
    apply();
});
