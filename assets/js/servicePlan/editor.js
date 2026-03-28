function normalizeSearchValue(value) {
    return (value || '')
        .toString()
        .trim()
        .toLocaleLowerCase();
}

function createSearchInput(select) {
    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'form-control searchable-select__input';
    input.placeholder = select.dataset.searchPlaceholder || 'Filter options';
    input.setAttribute('aria-label', select.dataset.searchPlaceholder || 'Filter options');

    const status = document.createElement('small');
    status.className = 'form-text text-muted searchable-select__status';
    status.hidden = true;

    const parent = select.parentNode;
    parent.insertBefore(wrapper, select);
    wrapper.appendChild(input);
    wrapper.appendChild(select);
    wrapper.appendChild(status);

    return { input, status };
}

function updateSearchableSelect(select, input, status) {
    const term = normalizeSearchValue(input.value);
    const options = Array.from(select.options);
    let matchCount = 0;

    options.forEach((option) => {
        if (option.value === '') {
            option.hidden = false;
            return;
        }

        const matches = normalizeSearchValue(option.textContent).includes(term);
        const isSelected = option.selected;

        option.hidden = term !== '' && !matches && !isSelected;
        option.disabled = term !== '' && !matches && !isSelected;

        if (!option.hidden) {
            matchCount += 1;
        }
    });

    if (term !== '' && matchCount === 0) {
        status.hidden = false;
        status.textContent = select.dataset.searchEmpty || 'No matching options';
    } else {
        status.hidden = true;
        status.textContent = '';
    }
}

function filterServicePlanModels(manufacturerSelect, modelSelect, shouldNavigate) {
    const selectedManufacturerId = manufacturerSelect.value;
    let hasVisibleSelectedModel = false;
    let firstVisibleValue = '';

    Array.from(modelSelect.options).forEach((option) => {
        const matchesManufacturer = option.dataset.manufacturerId === selectedManufacturerId;

        option.hidden = !matchesManufacturer;
        option.disabled = !matchesManufacturer;

        if (!matchesManufacturer) {
            return;
        }

        if (firstVisibleValue === '') {
            firstVisibleValue = option.value;
        }

        if (option.selected) {
            hasVisibleSelectedModel = true;
        }
    });

    if (!hasVisibleSelectedModel && firstVisibleValue !== '') {
        modelSelect.value = firstVisibleValue;

        if (shouldNavigate) {
            window.location.assign(firstVisibleValue);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-service-plan-manufacturer-selector="true"]').forEach((manufacturerSelect) => {
        const modelSelect = document.querySelector('[data-service-plan-model-selector="true"]');

        if (!modelSelect) {
            return;
        }

        filterServicePlanModels(manufacturerSelect, modelSelect, false);

        manufacturerSelect.addEventListener('change', () => {
            filterServicePlanModels(manufacturerSelect, modelSelect, true);
        });
    });

    document.querySelectorAll('select[data-service-plan-model-selector="true"]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.value !== '') {
                window.location.assign(select.value);
            }
        });
    });

    document.querySelectorAll('select[data-searchable-select="true"]').forEach((select) => {
        if (select.dataset.searchableSelectInitialized === 'true') {
            return;
        }

        const { input, status } = createSearchInput(select);
        input.addEventListener('input', () => updateSearchableSelect(select, input, status));

        select.dataset.searchableSelectInitialized = 'true';
        updateSearchableSelect(select, input, status);
    });
});
