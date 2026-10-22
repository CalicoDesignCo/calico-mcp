/* Selection is local to the form until the administrator chooses Save access. */
(function () {
    'use strict';
    var editing = ['content', 'media', 'seo', 'events', 'elementor', 'structure', 'terms', 'seo_advanced', 'elementor_full', 'published', 'repeater_rows', 'shared_templates', 'organization'];
    document.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-cmcp-select]');
        if (!button) return;
        var group = button.closest('[data-cmcp-selection]');
        if (!group) return;
        var isEditing = group.getAttribute('data-cmcp-selection') === 'editing';
        var selected = button.getAttribute('data-cmcp-select') === 'all';
        var count = 0;
        group.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
            if (input.disabled || (isEditing ? editing.indexOf(input.name) === -1 : input.name.indexOf('event_types[') !== 0)) return;
            input.checked = selected;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            count++;
        });
        var actions = button.closest('.cmcp-selection-actions');
        var status = actions && actions.querySelector('[data-cmcp-selection-status]');
        if (status) status.textContent = (selected ? 'Selected ' : 'Cleared ') + count + ' choices. Save access to apply.';
    });
}());

