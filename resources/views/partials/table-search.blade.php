{{-- Live table filter.

     Client-side on purpose. Every screen that includes this renders its WHOLE result
     set, so hiding rows as you type filters everything there is -- no round trip, no
     spinner, and it keeps working while the free-tier dyno is cold. Trips is the one
     exception: it paginates at 20, so filtering what is on the page would quietly lie
     about the rest, and it searches server-side instead.

     Expects: $placeholder. Sits inside the table's own .card, directly above the
     table it filters, and finds that table itself -- no ids to keep in sync. --}}
<div class="p-3 border-bottom table-search">
    <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="bi bi-search"></i>
        </span>
        <input type="search"
               class="form-control border-start-0 ps-0"
               data-table-search
               placeholder="{{ $placeholder ?? 'Search…' }}"
               aria-label="{{ $placeholder ?? 'Search this table' }}"
               autocomplete="off">
        <button type="button" class="btn btn-outline-secondary d-none"
                data-table-search-clear aria-label="Clear search">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    {{-- Hiding rows is a silent change to a screen reader, so the count is announced.
         polite, so it waits for a pause in typing rather than interrupting. --}}
    <div class="small text-muted mt-2 d-none" data-table-search-status
         role="status" aria-live="polite"></div>
</div>
