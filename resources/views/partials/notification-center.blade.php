<details class="notification-center" id="notification-center">
    <summary class="btn btn-outline-secondary notification-toggle" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        <span class="d-none d-sm-inline">Notifications</span>
        <span id="notification-count" class="badge notification-count" hidden>0</span>
    </summary>
    <section class="notification-panel" aria-label="Notification center">
        <div class="notification-panel-heading"><div><strong>Notifications</strong><small>Updates &amp; payment reminders</small></div><button type="button" class="btn-close" id="close-notification-center" aria-label="Close notifications"></button></div>
        <div id="notification-list"><div id="notification-empty" class="notification-empty"><span class="notification-empty-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></span><strong>You're all caught up</strong><p class="text-muted mb-0">No new notifications.</p></div></div>
        <a class="notification-history" href="{{ route('transactions.index') }}">View transaction history <span aria-hidden="true">&rarr;</span></a>
    </section>
</details>
