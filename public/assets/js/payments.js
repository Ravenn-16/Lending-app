document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('payment-form');
    if (!form) return;
    const borrower = document.getElementById('borrower_select');
    const loan = document.getElementById('loan_id');
    const date = document.getElementById('payment_date');
    const button = document.getElementById('payInFullBtn');
    const save = document.getElementById('savePaymentBtn');
    const amount = document.getElementById('payment_amount');
    const info = document.getElementById('loanInfo');
    const balance = document.getElementById('currentBalance');
    const status = document.getElementById('loanStatus');
    let listRequest = 0, infoRequest = 0, payoff = null;
    const json = async url => {
        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Unable to load loan details.');
        return data;
    };
    const update = async () => {
        const request = ++infoRequest;
        payoff = null; button.disabled = true; save.disabled = true; info.classList.add('d-none');
        if (!loan.value || !date.value) return;
        try {
            const data = await json(form.dataset.infoUrl.replace('__ID__', loan.value) + '?payment_date=' + encodeURIComponent(date.value));
            if (request !== infoRequest) return;
            balance.textContent = 'Balance on selected date: ' + data.balance_display;
            status.textContent = data.status;
            payoff = Number(data.payoff_amount); button.disabled = payoff <= 0;
            save.disabled = payoff <= 0;
            amount.max = Math.max(0, payoff).toFixed(2);
            date.min = data.date_borrowed;
            if (payoff <= 0) status.textContent += '. No payment is due on this date.';
            info.classList.remove('d-none');
        } catch (error) {
            if (request !== infoRequest) return;
            window.showMessage?.(error.message, 'error'); balance.textContent = error.message; status.textContent = ''; info.classList.remove('d-none');
        }
    };
    const load = async (selected = '') => {
        const request = ++listRequest; ++infoRequest;
        button.disabled = true; save.disabled = true; payoff = null; info.classList.add('d-none');
        date.min = '1900-01-01';
        loan.replaceChildren(new Option('Choose a loan', ''));
        if (!borrower.value) return;
        try {
            const data = await json(form.dataset.loansUrl.replace('__ID__', borrower.value));
            if (request !== listRequest) return;
            data.forEach(item => loan.add(new Option(item.text, item.id)));
            if (selected) { loan.value = selected; await update(); }
        } catch (error) {
            if (request !== listRequest) return;
            window.showMessage?.(error.message, 'error'); balance.textContent = error.message; status.textContent = ''; info.classList.remove('d-none');
        }
    };
    borrower.addEventListener('change', () => load());
    loan.addEventListener('change', update); date.addEventListener('change', update);
    button.addEventListener('click', () => { if (payoff > 0) document.getElementById('payment_amount').value = payoff.toFixed(2); });
    load(form.dataset.selectedLoan);
});
