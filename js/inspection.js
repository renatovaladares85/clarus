// SPDX-License-Identifier: GPL-3.0-or-later

(function () {
    'use strict';

    function normalize(value) {
        return value.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function numberValue(rule, field) {
        return Number(rule.dataset[field] || '0');
    }

    function adherenceDenominator(rule) {
        return Math.max(1, numberValue(rule, 'adherenceDenominator'));
    }

    function compareAdherence(left, right) {
        return (numberValue(left, 'adherenceNumerator') * adherenceDenominator(right))
            - (numberValue(right, 'adherenceNumerator') * adherenceDenominator(left));
    }

    function compareValue(left, right, field) {
        if (['entity', 'name'].includes(field)) {
            return (left.dataset[field] || '').localeCompare(right.dataset[field] || '');
        }
        if (field === 'condition') {
            return (left.dataset.condition || '') === (right.dataset.condition || '')
                ? 0
                : (left.dataset.condition === 'onadd' ? -1 : 1);
        }
        if (field === 'result') {
            return numberValue(left, 'evaluationOrder') - numberValue(right, 'evaluationOrder');
        }
        if (field === 'adherence') {
            return compareAdherence(left, right);
        }
        return numberValue(left, field) - numberValue(right, field);
    }

    function compareRules(left, right, criteria) {
        for (const criterion of criteria) {
            const difference = compareValue(left, right, criterion.field);
            if (difference !== 0) {
                return criterion.direction === 'desc' ? -difference : difference;
            }
        }
        const rankingDifference = numberValue(left, 'ranking') - numberValue(right, 'ranking');
        return rankingDifference || numberValue(left, 'id') - numberValue(right, 'id');
    }

    function matchesFilters(rule, state) {
        const query = normalize(state.query.trim());

        return state.results.has(rule.dataset.evaluation)
            && state.conditions.has(rule.dataset.condition)
            && state.entities.has(rule.dataset.entityId)
            && (numberValue(rule, 'adherenceNumerator') * 100)
                >= (state.minimumAdherence * adherenceDenominator(rule))
            && normalize(rule.dataset.search || '').includes(query);
    }

    function filterAndSortRules(rules, state, criteria) {
        return rules.filter((rule) => matchesFilters(rule, state)).sort((left, right) => compareRules(left, right, criteria));
    }

    function paginate(rules, page, pageSize) {
        const pageCount = Math.max(1, Math.ceil(rules.length / pageSize));
        const currentPage = Math.min(page, pageCount);
        const first = (currentPage - 1) * pageSize;

        return {
            currentPage,
            first,
            last: Math.min(first + pageSize, rules.length),
            pageCount,
            rules: rules.slice(first, first + pageSize),
        };
    }

    const behavior = {filterAndSortRules, paginate};
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = behavior;
    }

    function initialize(container) {
        if (container.dataset.clarusInitialized === 'true') {
            return;
        }
        container.dataset.clarusInitialized = 'true';

        const rulesHost = container.querySelector('[data-clarus-rules]');
        if (!rulesHost) {
            return;
        }

        const state = {
            conditions: new Set(['onadd', 'onupdate']),
            entities: new Set(),
            group: container.dataset.initialGroup || 'processing',
            minimumAdherence: Number(container.dataset.minimumAdherence || '0'),
            page: 1,
            pageSize: Number.parseInt(container.dataset.pageSize || '25', 10),
            query: '',
            results: new Set(['match', 'no_match', 'indeterminate']),
        };

        const allRules = Array.from(rulesHost.querySelectorAll('[data-clarus-rule]'));
        const search = container.querySelector('[data-clarus-search]');
        const group = container.querySelector('[data-clarus-group]');
        const resultInputs = Array.from(container.querySelectorAll('[data-clarus-result]'));
        const resultAll = container.querySelector('[data-clarus-result-all]');
        const conditionSelect = container.querySelector('[data-clarus-condition]');
        const entitySelect = container.querySelector('[data-clarus-entity]');
        const sortFields = Array.from(container.querySelectorAll('[data-clarus-sort-field]'));
        const sortDirections = Array.from(container.querySelectorAll('[data-clarus-sort-direction]'));

        function sortCriteria() {
            const seen = new Set();
            return sortFields.reduce(function (criteria, field, index) {
                const value = field.value;
                if (value && !seen.has(value)) {
                    seen.add(value);
                    criteria.push({field: value, direction: sortDirections[index]?.value === 'desc' ? 'desc' : 'asc'});
                }
                return criteria;
            }, []);
        }

        function groupValue(rule) {
            if (state.group === 'result') {
                return rule.querySelector('.clarus-rule__badge')?.textContent?.trim() || '';
            }
            if (state.group === 'entity') {
                return rule.dataset.entityLabel || '';
            }
            return '';
        }

        function renderGroups(pageRules) {
            rulesHost.querySelectorAll('[data-clarus-group-heading]').forEach((heading) => heading.remove());
            if (state.group === 'processing') {
                return;
            }

            let previous = null;
            pageRules.forEach((rule) => {
                const value = groupValue(rule);
                if (value === previous) {
                    return;
                }
                const heading = document.createElement('h4');
                heading.className = 'clarus-inspection__group-heading';
                heading.dataset.clarusGroupHeading = 'true';
                heading.textContent = value;
                rule.before(heading);
                previous = value;
            });
        }

        function renderPagination(pageCount, visibleCount, first, last) {
            const pagination = container.querySelector('[data-clarus-pagination]');
            const summary = container.querySelector('[data-clarus-page-summary]');
            if (!pagination || !summary) {
                return;
            }

            summary.textContent = visibleCount === 0
                ? `0 ${container.dataset.labelRules}`
                : `${container.dataset.labelShowing} ${first + 1}–${last} ${container.dataset.labelOf} ${visibleCount} ${container.dataset.labelRules}`;
            pagination.replaceChildren();

            const makeButton = function (label, page, disabled, active, ariaLabel) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `btn btn-sm${active ? ' btn-primary' : ' btn-outline-secondary'}`;
                button.textContent = label;
                button.disabled = disabled;
                button.dataset.clarusPage = String(page);
                if (ariaLabel) {
                    button.setAttribute('aria-label', ariaLabel);
                }
                if (active) {
                    button.setAttribute('aria-current', 'page');
                }
                pagination.append(button);
            };

            if (pageCount > 1) {
                makeButton('‹', state.page - 1, state.page === 1, false, container.dataset.labelPrevious);
                const pages = new Set([1, pageCount]);
                for (let page = Math.max(1, state.page - 2); page <= Math.min(pageCount, state.page + 2); page += 1) {
                    pages.add(page);
                }
                let previousPage = 0;
                Array.from(pages).sort((left, right) => left - right).forEach((page) => {
                    if (previousPage > 0 && page - previousPage > 1) {
                        makeButton('…', 0, true, false, null);
                    }
                    makeButton(String(page), page, false, page === state.page, null);
                    previousPage = page;
                });
                makeButton('›', state.page + 1, state.page === pageCount, false, container.dataset.labelNext);
            }
        }

        function apply() {
            const visible = filterAndSortRules(allRules, state, sortCriteria());
            visible.forEach((rule) => rulesHost.append(rule));
            const visibleSet = new Set(visible);
            const paginationState = paginate(visible, state.page, state.pageSize);
            state.page = paginationState.currentPage;
            const {first, last, pageCount, rules: pageRules} = paginationState;
            const pageRuleSet = new Set(pageRules);

            allRules.forEach((rule) => {
                rule.hidden = !visibleSet.has(rule) || !pageRuleSet.has(rule);
            });
            renderGroups(pageRules);

            const empty = container.querySelector('[data-clarus-filter-empty]');
            if (empty) {
                empty.classList.toggle('d-none', visible.length !== 0);
            }
            const count = container.querySelector('[data-clarus-visible-count]');
            if (count) {
                count.textContent = `${visible.length} ${container.dataset.labelRules}`;
            }
            renderPagination(pageCount, visible.length, first, last);
        }

        function refreshSet(inputs, set) {
            set.clear();
            inputs.filter((input) => input.getAttribute('aria-pressed') === 'true').forEach((input) => set.add(input.value));
        }

        function refreshConditions() {
            state.conditions.clear();
            if (!conditionSelect) {
                return;
            }

            Array.from(conditionSelect.selectedOptions).forEach((option) => state.conditions.add(option.value));
        }

        function refreshEntities() {
            state.entities.clear();
            if (entitySelect) {
                Array.from(entitySelect.selectedOptions).forEach((option) => state.entities.add(option.value));
            }
        }

        refreshEntities();

        if (search) {
            search.addEventListener('input', function () {
                state.query = search.value;
                state.page = 1;
                apply();
            });
        }
        if (group) {
            group.addEventListener('change', function () {
                state.group = group.value;
                state.page = 1;
                apply();
            });
        }
        resultInputs.forEach((input) => input.addEventListener('click', function () {
            input.setAttribute('aria-pressed', input.getAttribute('aria-pressed') !== 'true' ? 'true' : 'false');
            refreshSet(resultInputs, state.results);
            if (resultAll) {
                resultAll.setAttribute('aria-pressed', state.results.size === resultInputs.length ? 'true' : 'false');
            }
            state.page = 1;
            apply();
        }));
        if (resultAll) {
            resultAll.addEventListener('click', function () {
                const next = resultAll.getAttribute('aria-pressed') !== 'true';
                resultAll.setAttribute('aria-pressed', next ? 'true' : 'false');
                resultInputs.forEach((input) => input.setAttribute('aria-pressed', next ? 'true' : 'false'));
                refreshSet(resultInputs, state.results);
                state.page = 1;
                apply();
            });
        }
        if (conditionSelect) {
            conditionSelect.addEventListener('change', function () {
                refreshConditions();
                state.page = 1;
                apply();
            });
        }
        if (entitySelect) {
            entitySelect.addEventListener('change', function () {
                refreshEntities();
                state.page = 1;
                apply();
            });
        }
        [...sortFields, ...sortDirections].forEach((input) => input.addEventListener('change', function () {
            state.page = 1;
            apply();
        }));
        container.addEventListener('click', function (event) {
            const button = event.target.closest('[data-clarus-page]');
            if (!button || button.disabled) {
                return;
            }
            state.page = Number.parseInt(button.dataset.clarusPage, 10);
            apply();
            container.querySelector('.clarus-inspection__results-heading')?.scrollIntoView({block: 'nearest'});
        });

        apply();
    }

    async function refresh(form) {
        const container = form.closest('[data-clarus-inspection]');
        const button = form.querySelector('[data-clarus-refresh-button]');
        if (!container || !button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const label = button.querySelector('span');
        if (label) {
            label.textContent = button.dataset.busyLabel;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            const html = await response.text();
            if (!response.ok || html.trim() === '') {
                throw new Error('refresh-failed');
            }
            const fragment = document.createRange().createContextualFragment(html);
            const replacement = fragment.querySelector('[data-clarus-inspection]');
            if (!replacement) {
                throw new Error('invalid-response');
            }
            container.replaceWith(replacement);
            initialize(replacement);
        } catch (error) {
            const alert = container.querySelector('[data-clarus-error]');
            if (alert) {
                alert.classList.remove('d-none');
            }
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (label) {
                label.textContent = button.dataset.idleLabel;
            }
        }
    }

    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-clarus-refresh-form]');
        if (!form) {
            return;
        }
        event.preventDefault();
        refresh(form);
    });

    function initializeAll(root) {
        if (root.matches?.('[data-clarus-inspection]')) {
            initialize(root);
        }
        root.querySelectorAll?.('[data-clarus-inspection]').forEach(initialize);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeAll(document);
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    initializeAll(node);
                }
            }));
        });
        observer.observe(document.body, {childList: true, subtree: true});
    });
}());
