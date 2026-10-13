document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('address-fields');
    if (!box) return;
    const province = document.getElementById('province_code');
    const city = document.getElementById('city_code');
    const barangay = document.getElementById('barangay_code');
    const status = document.getElementById('address-status');
    const retry = document.getElementById('address-retry');
    let generation = 0, retryAction = null;
    const reset = (select, label) => {
        select.replaceChildren(new Option(label, ''));
        select.disabled = true;
        select.required = Boolean(province.value);
    };
    const fill = (select, rows, value = '') => {
        rows.forEach(row => select.add(new Option(row.name, row.code)));
        select.disabled = false;
        select.value = value;
    };
    const fetchRows = async (url, params) => {
        const response = await fetch(url + '?' + new URLSearchParams(params), {headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Unable to load addresses. Please retry.');
        return response.json();
    };
    const message = text => { status.textContent = text; retry.hidden = true; };
    const loadBarangays = async (selected = '') => {
        const current = ++generation;
        reset(barangay, 'Choose a barangay');
        if (!city.value) { message('Choose a city or municipality.'); return; }
        message('Loading barangays...');
        try {
            const rows = await fetchRows(box.dataset.barangaysUrl, {province_code: province.value, city_code: city.value});
            if (current !== generation) return;
            fill(barangay, rows, selected); message('');
        } catch (error) {
            if (current !== generation) return;
            window.showMessage?.(error.message, 'error'); message(error.message); retry.hidden = false; retryAction = () => loadBarangays(selected);
        }
    };
    const loadCities = async (selectedCity = '', selectedBarangay = '') => {
        const current = ++generation;
        reset(city, 'Choose a city or municipality'); reset(barangay, 'Choose a barangay');
        if (!province.value) { message('Location is optional. Select all three fields when providing an address.'); return; }
        message('Loading cities and municipalities...');
        try {
            const rows = await fetchRows(box.dataset.citiesUrl, {province_code: province.value});
            if (current !== generation) return;
            fill(city, rows, selectedCity); message('Choose a city or municipality.');
            if (city.value) await loadBarangays(selectedBarangay);
        } catch (error) {
            if (current !== generation) return;
            window.showMessage?.(error.message, 'error'); message(error.message); retry.hidden = false; retryAction = () => loadCities(selectedCity, selectedBarangay);
        }
    };
    province.addEventListener('change', () => loadCities());
    city.addEventListener('change', () => loadBarangays());
    retry.addEventListener('click', () => retryAction?.());
    box.closest('form').addEventListener('submit', event => {
        if (province.value && (!city.value || !barangay.value || city.disabled || barangay.disabled)) {
            event.preventDefault(); window.showMessage?.('Select a city or municipality and barangay before saving.', 'error'); message('Select a city or municipality and barangay before saving.');
        }
    });
    // Initial options are rendered by Laravel, including old input after validation.
});
