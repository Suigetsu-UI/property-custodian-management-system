(function () {
    'use strict';

    var minimumCharacters = 2;
    var debounceMilliseconds = 250;

    function initializeTypeahead(container) {
        var input = container.querySelector('input[data-asset-suggestion-input]');
        var listbox = container.querySelector('[role="listbox"]');
        var field = container.dataset.suggestionField;
        var endpoint = container.dataset.suggestionsUrl || 'get_suggestions.php';

        if (!input || !listbox || !field) return;

        var suggestions = [];
        var activeIndex = -1;
        var debounceTimer = null;
        var requestController = null;

        function closeSuggestions() {
            suggestions = [];
            activeIndex = -1;
            listbox.replaceChildren();
            listbox.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function setActiveIndex(index) {
            var options = listbox.querySelectorAll('[role="option"]');

            if (!options.length) {
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
                return;
            }

            activeIndex = (index + options.length) % options.length;

            options.forEach(function (option, optionIndex) {
                var selected = optionIndex === activeIndex;

                option.setAttribute('aria-selected', selected ? 'true' : 'false');
                option.classList.toggle('is-active', selected);
            });

            input.setAttribute(
                'aria-activedescendant',
                options[activeIndex].id
            );
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function selectSuggestion(index) {
            if (!suggestions[index]) return;

            input.value = suggestions[index];
            input.dispatchEvent(new Event('change', { bubbles: true }));
            closeSuggestions();
            input.focus();
        }

        function renderSuggestions(values) {
            suggestions = values.filter(function (value) {
                return typeof value === 'string' && value.trim() !== '';
            });
            activeIndex = -1;
            listbox.replaceChildren();

            if (!suggestions.length) {
                closeSuggestions();
                return;
            }

            suggestions.forEach(function (value, index) {
                var option = document.createElement('button');

                option.type = 'button';
                option.id = listbox.id + '-option-' + index;
                option.className = 'pcms-typeahead-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = value;

                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });

                option.addEventListener('click', function () {
                    selectSuggestion(index);
                });

                listbox.appendChild(option);
            });

            listbox.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        async function loadSuggestions() {
            var query = input.value.trim();

            if (query.length < minimumCharacters) {
                closeSuggestions();
                return;
            }

            if (requestController) requestController.abort();
            requestController = new AbortController();

            try {
                var url = endpoint + '?' + new URLSearchParams({
                    field: field,
                    q: query
                }).toString();
                var response = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' },
                    signal: requestController.signal
                });

                if (!response.ok) throw new Error('Suggestion request failed.');

                var data = await response.json();

                if (input.value.trim() !== query) return;

                renderSuggestions(
                    data && Array.isArray(data.suggestions)
                        ? data.suggestions
                        : []
                );
            } catch (error) {
                if (error.name !== 'AbortError') closeSuggestions();
            }
        }

        function scheduleSuggestions() {
            window.clearTimeout(debounceTimer);

            if (input.value.trim().length < minimumCharacters) {
                if (requestController) requestController.abort();
                closeSuggestions();
                return;
            }

            debounceTimer = window.setTimeout(
                loadSuggestions,
                debounceMilliseconds
            );
        }

        input.addEventListener('input', scheduleSuggestions);

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= minimumCharacters) {
                scheduleSuggestions();
            }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (!listbox.hidden) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeSuggestions();
                }
                return;
            }

            if (listbox.hidden || !suggestions.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveIndex(activeIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveIndex(activeIndex - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                selectSuggestion(activeIndex);
            }
        });

        container.closest('form')?.addEventListener('reset', function () {
            window.setTimeout(closeSuggestions, 0);
        });

        document.addEventListener('mousedown', function (event) {
            if (!container.contains(event.target)) closeSuggestions();
        });
    }

    document.querySelectorAll('[data-asset-typeahead]').forEach(
        initializeTypeahead
    );
})();
