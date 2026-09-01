<?php
/** Styles + scroll hint for Key analytics table (stats.php). */
?>
<style>
.key-analytics-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    border-radius: 12px;
    margin-top: 0.25rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
}
.key-analytics-scroll::-webkit-scrollbar {
    height: 6px;
}
.key-analytics-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 999px;
}
.key-analytics-table {
    min-width: 980px;
    margin-bottom: 0 !important;
}
.key-analytics-table th.col-actions,
.key-analytics-table td.col-actions {
    position: sticky;
    right: 0;
    z-index: 2;
    background: rgba(18, 20, 26, 0.98);
    box-shadow: -10px 0 16px rgba(0, 0, 0, 0.35);
    min-width: 168px;
}
.key-analytics-table thead th.col-actions {
    background: rgba(10, 12, 16, 0.98);
}
.key-analytics-actions {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.35rem;
    align-items: center;
    justify-content: flex-start;
}
.key-analytics-actions .btn {
    padding: 0.38rem 0.55rem !important;
    font-size: 0.72rem !important;
    line-height: 1.2 !important;
    white-space: nowrap;
    border-radius: 8px !important;
}
.key-analytics-scroll-hint {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 0.65rem;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.45);
}
@media (max-width: 991.98px) {
    .key-analytics-scroll-hint {
        display: flex;
    }
    .admin-table-wrap.key-analytics-wrap {
        overflow: visible;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.key-analytics-scroll');
    var hint = document.querySelector('.key-analytics-scroll-hint');
    if (!wrap || !hint) return;
    function updateHint() {
        var canScroll = wrap.scrollWidth > wrap.clientWidth + 8;
        hint.style.display = canScroll && window.innerWidth < 992 ? 'flex' : 'none';
    }
    updateHint();
    window.addEventListener('resize', updateHint);
    wrap.addEventListener('scroll', function () {
        if (wrap.scrollLeft > 12) {
            hint.style.opacity = '0.35';
        }
    }, { passive: true });
});
</script>
