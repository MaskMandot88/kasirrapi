(function () {
    const nativeAlert = window.alert;
    const nativeConfirm = window.confirm;

    const AppUI = {
        showLoading(message) {
            const loader = document.getElementById('globalLoader');
            const text = document.getElementById('globalLoaderText');

            if (text && message) text.textContent = message;
            if (loader) loader.classList.add('active');
        },

        hideLoading() {
            const loader = document.getElementById('globalLoader');
            if (loader) loader.classList.remove('active');
        },

        toast(message, icon = 'success') {
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title: message,
                    showConfirmButton: false,
                    timer: 2600,
                    timerProgressBar: true,
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ff6a00'
                });
                return;
            }

            nativeAlert(message);
        },

        alert(message, icon = 'info', title = '') {
            if (window.Swal) {
                Swal.fire({
                    icon,
                    title: title || (
                        icon === 'success' ? 'Berhasil' :
                        icon === 'error' ? 'Gagal' :
                        icon === 'warning' ? 'Peringatan' :
                        'Informasi'
                    ),
                    text: String(message),
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff6a00',
                    background: '#0f172a',
                    color: '#e2e8f0'
                });
                return;
            }

            nativeAlert(message);
        },

        confirm(message, callback, title = 'Konfirmasi') {
            if (window.Swal) {
                Swal.fire({
                    icon: 'question',
                    title,
                    text: String(message),
                    showCancelButton: true,
                    confirmButtonText: 'Ya, lanjut',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#ff6a00',
                    cancelButtonColor: '#475569',
                    background: '#0f172a',
                    color: '#e2e8f0'
                }).then(result => {
                    if (result.isConfirmed && typeof callback === 'function') {
                        callback();
                    }
                });
                return;
            }

            if (nativeConfirm(message) && typeof callback === 'function') {
                callback();
            }
        }
    };

    window.AppUI = AppUI;

    // alert("...") lama otomatis jadi SweetAlert
    window.alert = function (message) {
        AppUI.alert(message, 'info', 'Informasi');
    };

    function extractConfirmMessage(onclickText) {
        if (!onclickText) return 'Lanjutkan proses ini?';

        let match =
            onclickText.match(/confirm\s*\(\s*'([^']*)'\s*\)/) ||
            onclickText.match(/confirm\s*\(\s*"([^"]*)"\s*\)/);

        return match ? match[1] : 'Lanjutkan proses ini?';
    }

    function convertInlineConfirm() {
        document.querySelectorAll('[onclick*="confirm"]').forEach(el => {
            const onclickText = el.getAttribute('onclick') || '';
            const message = extractConfirmMessage(onclickText);

            el.dataset.confirm = message;
            el.removeAttribute('onclick');
        });
    }

    function bindSweetConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            if (el.dataset.confirmBound === '1') return;
            el.dataset.confirmBound = '1';

            el.addEventListener('click', function (event) {
                event.preventDefault();

                const message = el.dataset.confirm || 'Lanjutkan proses ini?';
                const href = el.getAttribute('href');

                AppUI.confirm(message, function () {
                    if (href && href !== '#') {
                        window.location.href = href;
                        return;
                    }

                    if (el.tagName === 'BUTTON' && el.form) {
                        AppUI.showLoading('Memproses data...');
                        el.form.submit();
                    }
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('page-enter');

        convertInlineConfirm();
        bindSweetConfirm();

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                if (form.dataset.noLoading === '1') return;
                AppUI.showLoading(form.dataset.loadingText || 'Memproses data...');
            });
        });

        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function () {
                if (link.dataset.confirm) return;

                const href = link.getAttribute('href') || '';
                const target = link.getAttribute('target');

                if (
                    !href ||
                    href.startsWith('#') ||
                    href.startsWith('javascript:') ||
                    href.startsWith('mailto:') ||
                    href.startsWith('tel:') ||
                    target === '_blank' ||
                    link.hasAttribute('download') ||
                    /(?:\?|&)export=/.test(href) ||
                    /\.(xls|xlsx|csv|pdf|zip)(?:[?#]|$)/i.test(href) ||
                    link.dataset.noLoading === '1'
                ) {
                    return;
                }

                AppUI.showLoading('Membuka halaman...');
            });
        });

        const flash = document.getElementById('appFlashData');

        if (flash) {
            const success = flash.dataset.success;
            const error = flash.dataset.error;
            const info = flash.dataset.info;
            const warning = flash.dataset.warning;

            if (success) AppUI.toast(success, 'success');
            if (error) AppUI.alert(error, 'error', 'Terjadi Kesalahan');
            if (warning) AppUI.alert(warning, 'warning', 'Peringatan');
            if (info) AppUI.alert(info, 'info', 'Informasi');
        }
    });

    window.addEventListener('pageshow', function () {
        AppUI.hideLoading();
    });
})();
