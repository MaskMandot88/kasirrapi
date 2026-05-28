(function () {
    const config = window.KasirRapiPwa || {};
    const appName = config.appName || 'KasirRapi';
    const today = new Date().toISOString().slice(0, 10);
    const shownKey = 'kasirrapi_install_prompt_shown_date';
    const installedKey = 'kasirrapi_install_prompt_installed';
    let deferredPrompt = null;
    let promptOpen = false;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: window-controls-overlay)').matches
            || window.navigator.standalone === true
            || document.referrer.startsWith('android-app://');
    }

    function storageGet(key) {
        try {
            return localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (error) {
            return false;
        }

        return true;
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !config.serviceWorkerUrl) return;

        window.addEventListener('load', function () {
            navigator.serviceWorker.register(config.serviceWorkerUrl).catch(function () {
                // Browser lama atau mode private bisa menolak service worker.
            });
        });
    }

    function canShowPrompt() {
        if (!deferredPrompt || promptOpen) return false;
        if (isStandalone()) return false;
        if (storageGet(installedKey) === '1') return false;
        return storageGet(shownKey) !== today;
    }

    function showInstallPrompt() {
        if (!canShowPrompt()) return;

        storageSet(shownKey, today);
        promptOpen = true;

        const message = 'Install ' + appName + ' di PC atau Android agar lebih cepat dibuka seperti aplikasi.';

        if (!window.Swal) {
            promptOpen = false;
            return;
        }

        Swal.fire({
            icon: 'info',
            title: 'Install ' + appName,
            text: message,
            showCancelButton: true,
            confirmButtonText: 'Download',
            cancelButtonText: 'Nanti',
            confirmButtonColor: '#ff6a00',
            cancelButtonColor: '#475569',
            background: '#0f172a',
            color: '#e2e8f0',
            heightAuto: false
        }).then(function (result) {
            promptOpen = false;

            if (!result.isConfirmed || !deferredPrompt) return;

            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choice) {
                if (choice.outcome === 'accepted') {
                    storageSet(installedKey, '1');
                }
                deferredPrompt = null;
            });
        });
    }

    registerServiceWorker();

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        setTimeout(showInstallPrompt, 900);
    });

    window.addEventListener('appinstalled', function () {
        storageSet(installedKey, '1');
        deferredPrompt = null;
    });
})();
