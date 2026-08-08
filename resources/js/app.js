import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

let notificationUi;
let confirmResolver;
const confirmedForms = new WeakSet();

function ensureNotificationUi() {
    if (notificationUi) return notificationUi;

    const root = document.createElement('div');
    root.innerHTML = `
        <div id="app-toast-container" class="pointer-events-none fixed right-4 top-4 z-[300] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3" aria-live="polite" aria-atomic="true"></div>
        <div id="app-confirm-modal" class="fixed inset-0 z-[310] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title">
            <button type="button" data-app-confirm-cancel class="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" aria-label="Tutup konfirmasi"></button>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-stone-900/10">
                <div class="p-6 text-center">
                    <div id="app-confirm-icon" class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-red-100 text-2xl font-black text-red-600">!</div>
                    <h2 id="app-confirm-title" class="mt-4 text-xl font-black text-stone-950">Konfirmasi tindakan</h2>
                    <p id="app-confirm-message" class="mt-2 text-sm leading-6 text-stone-600"></p>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-stone-200 bg-stone-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" data-app-confirm-cancel class="rounded-lg border border-stone-300 bg-white px-5 py-2.5 text-sm font-semibold text-stone-700 hover:bg-stone-100">Kembali</button>
                    <button id="app-confirm-submit" type="button" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700">Lanjutkan</button>
                </div>
            </div>
        </div>
        <div id="app-copy-modal" class="fixed inset-0 z-[320] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="app-copy-title">
            <button type="button" data-app-copy-close class="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" aria-label="Tutup"></button>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 id="app-copy-title" class="text-xl font-black text-stone-950">Salin kode aktivasi</h2>
                <p class="mt-2 text-sm text-stone-600">Kode telah dipilih. Tekan Ctrl+C, lalu tempelkan pada perangkat tujuan.</p>
                <input id="app-copy-value" type="text" readonly class="mt-5 block w-full rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 text-center font-mono text-lg font-black tracking-wider text-stone-950 focus:border-amber-500 focus:ring-amber-500">
                <button type="button" data-app-copy-close class="mt-5 w-full rounded-lg bg-stone-900 px-5 py-3 text-sm font-bold text-white hover:bg-stone-700">Selesai</button>
            </div>
        </div>`;
    document.body.appendChild(root);

    notificationUi = {
        toasts: document.getElementById('app-toast-container'),
        confirmModal: document.getElementById('app-confirm-modal'),
        confirmTitle: document.getElementById('app-confirm-title'),
        confirmMessage: document.getElementById('app-confirm-message'),
        confirmIcon: document.getElementById('app-confirm-icon'),
        confirmSubmit: document.getElementById('app-confirm-submit'),
        copyModal: document.getElementById('app-copy-modal'),
        copyTitle: document.getElementById('app-copy-title'),
        copyValue: document.getElementById('app-copy-value'),
    };

    root.querySelectorAll('[data-app-confirm-cancel]').forEach(button => button.addEventListener('click', () => closeConfirm(false)));
    notificationUi.confirmSubmit.addEventListener('click', () => closeConfirm(true));
    root.querySelectorAll('[data-app-copy-close]').forEach(button => button.addEventListener('click', () => {
        notificationUi.copyModal.classList.add('hidden');
        notificationUi.copyModal.classList.remove('flex');
    }));

    return notificationUi;
}

function closeConfirm(confirmed) {
    if (!notificationUi || !confirmResolver) return;

    notificationUi.confirmModal.classList.add('hidden');
    notificationUi.confirmModal.classList.remove('flex');
    const resolve = confirmResolver;
    confirmResolver = null;
    resolve(confirmed);
}

window.appToast = (message, type = 'info', options = {}) => {
    const ui = ensureNotificationUi();
    const styles = {
        success: {border: 'border-emerald-500', icon: 'bg-emerald-100 text-emerald-700', symbol: '✓', title: 'Berhasil', progress: '#10b981'},
        error: {border: 'border-red-500', icon: 'bg-red-100 text-red-700', symbol: '!', title: 'Tindakan gagal', progress: '#ef4444'},
        warning: {border: 'border-amber-500', icon: 'bg-amber-100 text-amber-700', symbol: '!', title: 'Perhatian', progress: '#f59e0b'},
        info: {border: 'border-blue-500', icon: 'bg-blue-100 text-blue-700', symbol: 'i', title: 'Informasi', progress: '#3b82f6'},
    };
    const style = styles[type] || styles.info;
    const toast = document.createElement('div');
    toast.className = `pointer-events-auto translate-x-8 overflow-hidden rounded-2xl border-l-4 ${style.border} bg-white opacity-0 shadow-2xl ring-1 ring-stone-900/10 transition duration-300`;
    toast.innerHTML = `
        <div class="flex gap-3 p-4">
            <div data-toast-icon class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-black"></div>
            <div class="min-w-0 flex-1"><p data-toast-title class="font-bold text-stone-950"></p><p data-toast-message class="mt-0.5 text-sm leading-5 text-stone-600"></p><a data-toast-action class="mt-2 hidden text-sm font-bold text-amber-700 hover:underline"></a></div>
            <button type="button" data-toast-close class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-lg text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Tutup">×</button>
        </div>
        <div data-toast-progress class="h-1 origin-left"></div>`;
    const icon = toast.querySelector('[data-toast-icon]');
    icon.className += ` ${style.icon}`;
    icon.textContent = style.symbol;
    toast.querySelector('[data-toast-title]').textContent = options.title || style.title;
    toast.querySelector('[data-toast-message]').textContent = message;
    const action = toast.querySelector('[data-toast-action]');
    if (options.url) {
        action.href = options.url;
        action.textContent = options.actionLabel || 'Lihat detail';
        action.classList.remove('hidden');
    }
    const progress = toast.querySelector('[data-toast-progress]');
    progress.style.backgroundColor = style.progress;
    const remove = () => {
        toast.classList.add('translate-x-8', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    };
    toast.querySelector('[data-toast-close]').addEventListener('click', remove);
    ui.toasts.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-8', 'opacity-0'));
    const duration = options.duration ?? 4500;
    progress.animate([{transform: 'scaleX(1)'}, {transform: 'scaleX(0)'}], {duration, easing: 'linear', fill: 'forwards'});
    setTimeout(remove, duration);
};

window.appConfirm = (options = {}) => {
    const ui = ensureNotificationUi();
    const config = typeof options === 'string' ? {message: options} : options;
    const danger = config.tone !== 'warning';
    ui.confirmTitle.textContent = config.title || 'Konfirmasi tindakan';
    ui.confirmMessage.textContent = config.message || 'Apakah Anda yakin ingin melanjutkan?';
    ui.confirmSubmit.textContent = config.confirmText || 'Lanjutkan';
    ui.confirmIcon.className = `mx-auto grid h-14 w-14 place-items-center rounded-full text-2xl font-black ${danger ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'}`;
    ui.confirmSubmit.className = `rounded-lg px-5 py-2.5 text-sm font-bold text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-500 hover:bg-amber-600'}`;
    ui.confirmModal.classList.remove('hidden');
    ui.confirmModal.classList.add('flex');
    setTimeout(() => ui.confirmSubmit.focus(), 0);

    return new Promise(resolve => {
        confirmResolver = resolve;
    });
};

window.appShowCopyText = (text, title = 'Salin kode aktivasi') => {
    const ui = ensureNotificationUi();
    ui.copyTitle.textContent = title;
    ui.copyValue.value = text;
    ui.copyModal.classList.remove('hidden');
    ui.copyModal.classList.add('flex');
    setTimeout(() => {
        ui.copyValue.focus();
        ui.copyValue.select();
    }, 0);
};

window.appLiveRegion = (selector, options = {}) => {
    const region = document.querySelector(selector);
    if (!region) return;

    let syncing = false;
    let currentHtml = region.innerHTML;
    const sync = async () => {
        if (syncing) return;
        syncing = true;

        try {
            const response = await fetch(options.url || window.location.href, {
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store',
            });
            if (!response.ok) return;
            const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextRegion = documentCopy.querySelector(selector);
            if (!nextRegion || nextRegion.innerHTML === currentHtml) return;

            currentHtml = nextRegion.innerHTML;
            region.innerHTML = currentHtml;
            region.dispatchEvent(new CustomEvent('app:live-updated'));
        } catch (error) {
            console.error(error);
        } finally {
            syncing = false;
        }
    };

    setInterval(sync, options.interval || 2000);
    sync();
};

window.copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch (error) {
            // Continue with the HTTP-compatible fallback below.
        }
    }

    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.readOnly = true;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        return document.execCommand('copy');
    } catch (error) {
        return false;
    } finally {
        textArea.remove();
    }
};

document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || confirmedForms.has(form)) {
        if (form) confirmedForms.delete(form);
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    window.appConfirm({
        title: form.dataset.confirmTitle,
        message: form.dataset.confirm,
        confirmText: form.dataset.confirmButton,
        tone: form.dataset.confirmTone,
    }).then(confirmed => {
        if (!confirmed) return;
        confirmedForms.add(form);
        form.requestSubmit(submitter || undefined);
    });
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && confirmResolver) closeConfirm(false);
});

document.addEventListener('DOMContentLoaded', () => {
    ensureNotificationUi();
    document.querySelectorAll('[data-app-flash]').forEach(flash => {
        window.appToast(flash.textContent.trim(), flash.dataset.type || 'info');
        flash.remove();
    });
});

Alpine.start();
