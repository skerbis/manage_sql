(function () {
    'use strict';

    function findAll(root, selector) {
        return (root || document).querySelectorAll(selector);
    }

    function findOne(root, selector) {
        return (root || document).querySelector(selector);
    }

    function initCreatePage(root) {
        if (!findOne(root, '#columns-container') || !findOne(root, '#column-template')) {
            return;
        }

        var container = findOne(root, '#columns-container');
        if (container && container.childElementCount === 0) {
            window.addColumn();
        }
    }

    function initQueryPage(root) {
        var selectAll = findOne(root, '#select_all');
        if (!selectAll) {
            return;
        }

        var columnList = findOne(root, '#column-list');
        if (columnList) {
            columnList.style.display = selectAll.checked ? 'none' : 'flex';
        }

        var columnCheckboxes = findAll(root, '.column-checkbox');
        columnCheckboxes.forEach(function (cb) {
            if (cb.dataset.manageSqlBound === '1') {
                return;
            }
            cb.dataset.manageSqlBound = '1';

            cb.addEventListener('change', function () {
                var allChecked = Array.from(columnCheckboxes).every(function (item) {
                    return item.checked;
                });
                var toggle = document.getElementById('select_all');
                if (toggle) {
                    toggle.checked = allChecked;
                }
            });
        });

        var queryType = findOne(root, '#query_type');
        if (queryType) {
            window.toggleQueryOptions(queryType.value);
        }
    }

    function initManageSqlUi(root) {
        initCreatePage(root);
        initQueryPage(root);
    }

    window.manageSqlCopyToClipboard = function (selector, message) {
        var target = document.querySelector(selector);
        if (!target) {
            return;
        }

        var text = target.textContent || '';
        if ('' === text) {
            return;
        }

        navigator.clipboard.writeText(text).then(function () {
            alert(message || 'In die Zwischenablage kopiert.');
        });
    };

    window.addColumn = function () {
        var container = document.getElementById('columns-container');
        var templateElement = document.getElementById('column-template');

        if (!container || !templateElement) {
            return;
        }

        if ('number' !== typeof window.manageSqlColumnIndex) {
            window.manageSqlColumnIndex = 0;
        }

        var template = templateElement.innerHTML;
        var html = template.replace(/{{index}}/g, String(window.manageSqlColumnIndex));
        window.manageSqlColumnIndex += 1;

        var temp = document.createElement('div');
        temp.innerHTML = html.trim();

        if (temp.firstElementChild) {
            container.appendChild(temp.firstElementChild);
        }
    };

    window.removeColumn = function (button) {
        var row = button ? button.closest('.column-row') : null;
        if (row) {
            row.remove();
        }
    };

    window.toggleQueryOptions = function (type) {
        var selectOptions = document.querySelectorAll('.select-options');
        var wherePanel = document.querySelector('.where-panel');

        selectOptions.forEach(function (el) {
            el.style.display = 'select' === type ? 'block' : 'none';
        });

        if (wherePanel) {
            wherePanel.style.display = ['select', 'update', 'delete'].indexOf(type) !== -1 ? 'block' : 'none';
        }
    };

    window.toggleAllColumns = function (checkbox) {
        var columnCheckboxes = document.querySelectorAll('.column-checkbox');
        var columnList = document.getElementById('column-list');

        columnCheckboxes.forEach(function (cb) {
            cb.checked = checkbox.checked;
        });

        if (columnList) {
            columnList.style.display = checkbox.checked ? 'none' : 'flex';
        }
    };

    window.addWhereRow = function () {
        var container = document.getElementById('where-conditions');
        if (!container) {
            return;
        }

        var template = container.querySelector('.where-row');
        if (!template) {
            return;
        }

        var row = template.cloneNode(true);
        row.querySelectorAll('select, input').forEach(function (el) {
            el.value = '';
        });
        container.appendChild(row);
    };

    window.removeWhereRow = function (button) {
        var row = button ? button.closest('.where-row') : null;
        if (!row) {
            return;
        }

        if (document.querySelectorAll('.where-row').length > 1) {
            row.remove();
        }
    };

    window.addOrderByRow = function () {
        var container = document.getElementById('orderby-conditions');
        if (!container) {
            return;
        }

        var template = container.querySelector('.orderby-row');
        if (!template) {
            return;
        }

        var row = template.cloneNode(true);
        row.querySelectorAll('select').forEach(function (el) {
            el.value = '';
        });
        container.appendChild(row);
    };

    window.removeOrderByRow = function (button) {
        var row = button ? button.closest('.orderby-row') : null;
        if (!row) {
            return;
        }

        if (document.querySelectorAll('.orderby-row').length > 1) {
            row.remove();
        }
    };

    if (window.jQuery) {
        window.jQuery(document).on('rex:ready', function (_event, container) {
            initManageSqlUi(container || document);
        });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            initManageSqlUi(document);
        });
    }
})();
