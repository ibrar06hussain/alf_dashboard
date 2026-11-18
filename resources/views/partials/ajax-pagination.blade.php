<style>
    .alf-ajax-paginating {
        opacity: 0.55;
        pointer-events: none;
        transition: opacity 0.15s ease-in-out;
    }
</style>
<script>
(function () {
    if (window.__alfAjaxPaginationBound) return;
    window.__alfAjaxPaginationBound = true;

    function isExcludedPagination(el) {
        if (!el) return true;
        return Boolean(
            el.closest('[data-no-ajax], #wheat-beneficiaries-container, .wheat-grid-pagination-links')
        );
    }

    function getActivePaginations(root) {
        return Array.from(root.querySelectorAll('.pagination, nav[role="navigation"]')).filter(function (pag) {
            if (isExcludedPagination(pag)) return false;
            return Boolean(pag.querySelector('a[href]'));
        });
    }

    function findPaginationContainer(pagEl) {
        if (!pagEl) return null;
        return pagEl.closest('[data-ajax-pagination], .card, .alf-panel, .tab-pane, #directory-section, section')
            || pagEl.parentElement;
    }

    function hoistModalsFromContainer(container) {
        if (!container) return;
        container.querySelectorAll('.modal').forEach(function (modal) {
            if (modal.id) {
                document.querySelectorAll('body > .modal#' + CSS.escape(modal.id)).forEach(function (existing) {
                    if (existing !== modal) existing.remove();
                });
            }
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    function addPaginationSpinner(pagEl) {
        if (!pagEl || pagEl.querySelector('.alf-pag-spinner')) return;
        var spinner = document.createElement('span');
        spinner.className = 'alf-pag-spinner spinner-border spinner-border-sm text-success ms-2 align-middle';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        pagEl.appendChild(spinner);
    }

    var activeController = null;

    function loadPageInPlace(url, container, pagIndex, containerId, pushHistory) {
        if (!container || !url) {
            window.location.href = url;
            return;
        }

        if (activeController && typeof activeController.abort === 'function') {
            activeController.abort();
        }
        activeController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        container.classList.add('alf-ajax-paginating');
        var pagEl = container.querySelector('.pagination, nav[role="navigation"]');
        addPaginationSpinner(pagEl);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            signal: activeController ? activeController.signal : undefined,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Alf-Ajax-Pagination': '1',
                'Accept': 'text/html'
            }
        })
        .then(function (response) {
            // If session expired and redirected to login, follow normal browser navigation
            if (response.redirected && response.url) {
                var targetPath = new URL(url, window.location.origin).pathname;
                var finalPath = new URL(response.url, window.location.origin).pathname;
                if (finalPath !== targetPath && finalPath.indexOf('login') !== -1) {
                    window.location.href = response.url;
                    return null;
                }
            }
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function (html) {
            if (html === null) return;

            var doc = new DOMParser().parseFromString(html, 'text/html');
            var replacement = null;

            if (containerId) {
                replacement = doc.getElementById(containerId);
            }

            if (!replacement && pagIndex >= 0) {
                var fetchedPags = getActivePaginations(doc);
                if (fetchedPags[pagIndex]) {
                    replacement = findPaginationContainer(fetchedPags[pagIndex]);
                }
            }

            if (!replacement) {
                window.location.href = url;
                return;
            }

            container.innerHTML = replacement.innerHTML;
            container.classList.remove('alf-ajax-paginating');
            hoistModalsFromContainer(container);

            if (pushHistory) {
                window.history.pushState({
                    alfAjaxPagination: true,
                    url: url,
                    pagIndex: pagIndex,
                    containerId: containerId
                }, '', url);
            }

            var rect = container.getBoundingClientRect();
            if (rect.top < 70 || rect.top > window.innerHeight * 0.75) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            document.dispatchEvent(new CustomEvent('alf:table-updated', {
                detail: { container: container, url: url }
            }));
        })
        .catch(function (err) {
            container.classList.remove('alf-ajax-paginating');
            if (err && err.name === 'AbortError') return;
            window.location.href = url;
        });
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        var resetLink = e.target.closest('a[data-ajax-reset][href]');
        if (resetLink) {
            var resetContainer = findPaginationContainer(resetLink) || resetLink.closest('.card, .alf-panel, section');
            if (resetContainer) {
                e.preventDefault();
                loadPageInPlace(resetLink.href, resetContainer, 0, resetContainer.id || '', true);
                return;
            }
        }

        var link = e.target.closest('.pagination a[href], nav[role="navigation"] a[href]');
        if (!link || link.getAttribute('target') === '_blank') return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;

        var pagEl = link.closest('.pagination, nav[role="navigation"]');
        if (!pagEl || isExcludedPagination(pagEl)) return;

        var container = findPaginationContainer(pagEl);
        if (!container) return;

        // Same-origin check
        try {
            var resolved = new URL(href, window.location.origin);
            if (resolved.origin !== window.location.origin) return;
        } catch (err) {
            return;
        }

        e.preventDefault();

        var allPags = getActivePaginations(document);
        var pagIndex = allPags.indexOf(pagEl);
        var containerId = container.id || '';

        loadPageInPlace(link.href, container, pagIndex, containerId, true);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-ajax-filter]');
        if (!form || e.defaultPrevented) return;
        var container = form.closest('[data-ajax-pagination], .card, .alf-panel, .tab-pane, #directory-section, section');
        if (!container) return;

        e.preventDefault();
        var actionUrl = form.getAttribute('action') || window.location.pathname;
        var urlObj = new URL(actionUrl, window.location.origin);
        var params = new URLSearchParams();
        new FormData(form).forEach(function (val, key) {
            if (String(val).trim() !== '') {
                params.append(key, String(val));
            }
        });
        urlObj.search = params.toString();
        loadPageInPlace(urlObj.toString(), container, 0, container.id || '', true);
    });

    window.addEventListener('popstate', function (e) {
        if (!e.state || !e.state.alfAjaxPagination) return;
        var container = e.state.containerId ? document.getElementById(e.state.containerId) : null;
        if (!container && typeof e.state.pagIndex === 'number') {
            var pags = getActivePaginations(document);
            if (pags[e.state.pagIndex]) {
                container = findPaginationContainer(pags[e.state.pagIndex]);
            }
        }
        if (container) {
            loadPageInPlace(window.location.href, container, e.state.pagIndex ?? 0, e.state.containerId || '', false);
        } else {
            window.location.reload();
        }
    });
})();
</script>
