const LIFE = 6000;
const FADE = 200;

export const showToasts = () => {
    const toasts = document.querySelector('.toasts');
    if (toasts?.children.length) {
        toasts.showPopover?.();
    }
    document.querySelectorAll('.toast').forEach(toast => setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), FADE);
    }, LIFE));
};
