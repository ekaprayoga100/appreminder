document.addEventListener('DOMContentLoaded', function() {
    ['to', 'cc'].forEach(function(type) {
        var searchInput = document.getElementById(type + 'Search');
        var listContainer = document.getElementById(type + 'EmailList');

        if (searchInput && listContainer) {
            searchInput.addEventListener('input', function() {
                var query = this.value.toLowerCase();
                var items = listContainer.querySelectorAll('.email-item');
                items.forEach(function(item) {
                    var name = item.getAttribute('data-name');
                    var email = item.getAttribute('data-email');
                    var dept = item.getAttribute('data-dept');
                    var visible = name.includes(query) || email.includes(query) || dept.includes(query);
                    item.style.display = visible ? '' : 'none';
                });
            });
        }

        renderEmailTags(type);
    });
});

function renderEmailTags(type) {
    var textarea = document.getElementById(type + 'Emails');
    var container = document.getElementById(type + 'Selected');
    if (!textarea || !container) return;
    container.innerHTML = '';
    var raw = textarea.value.trim();
    if (!raw) return;
    var emails = raw.split(/[,\n;]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    emails.forEach(function(email) {
        var tag = document.createElement('span');
        tag.className = 'selected-email-tag';
        tag.innerHTML = escapeHtml(email) + ' <button type="button" class="tag-remove" aria-label="Remove">&times;</button>';
        tag.setAttribute('data-email', email);
        tag.querySelector('.tag-remove').addEventListener('click', function() {
            removeEmailTag(type, email);
        });
        container.appendChild(tag);
    });
}

function removeEmailTag(type, email) {
    var textarea = document.getElementById(type + 'Emails');
    if (!textarea) return;
    var raw = textarea.value.trim();
    var emails = raw.split(/[,\n;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== email; });
    textarea.value = emails.join(', ');
    renderEmailTags(type);
}

function addSelectedEmails(type) {
    var textarea = document.getElementById(type + 'Emails');
    var modal = document.getElementById(type + 'EmailModal');
    var checks = modal.querySelectorAll('.email-check:checked');
    var values = [];
    checks.forEach(function(check) { values.push(check.value); });
    var current = textarea.value.trim();
    var selected = values.join(', ');
    if (current !== '' && selected !== '') {
        textarea.value = current + ', ' + selected;
    } else if (selected !== '') {
        textarea.value = selected;
    }
    renderEmailTags(type);
    bootstrap.Modal.getInstance(modal).hide();
    checks.forEach(function(check) { check.checked = false; });
}

function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
