/*
 * Reservations – frontend_body.js, two parts:
 * 1. With a choice of objects in the request form: shows the limits of the chosen
 *    object and sets them on the fields (dates, number of persons). Without
 *    JavaScript all limit lines stay visible and request.php checks anyway.
 * 2. Folds the request form behind its heading.
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

/*
 * The request form folds behind its heading (disclosure pattern: a button in
 * the heading keeps it reachable by heading navigation). Without JavaScript,
 * after sending and with errors it stays open; a jump to its anchor opens it.
 */
document.querySelectorAll('.rsv-request[data-fold]').forEach(function (section) {
    var heading = section.querySelector('h3');
    var body = heading.nextElementSibling;
    var anchor = '#' + section.getAttribute('data-fold');
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'rsv-fold';
    button.setAttribute('aria-controls', body.id);
    button.textContent = heading.textContent;
    heading.textContent = '';
    heading.appendChild(button);
    var toggle = function (open) {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        body.hidden = !open;
    };
    button.addEventListener('click', function () {
        toggle(body.hidden);
    });
    window.addEventListener('hashchange', function () {
        if (location.hash === anchor) {
            toggle(true);
        }
    });
    toggle(location.hash === anchor);
});
