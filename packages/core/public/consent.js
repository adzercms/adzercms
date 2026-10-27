import * as CookieConsent from 'vanilla-cookieconsent';

const NECESSARY = 'necessary';
const ANALYTICS = 'analytics';
const PROBE = 'adzercms_probe';

const config = JSON.parse(document.querySelector('script[data-consent]').dataset.consent);
const texts = config.texts;
const started = new Set();

const registrable = () => {
    const labels = location.hostname.split('.');
    for (let level = 2; level < labels.length; level++) {
        const domain = labels.slice(-level).join('.');
        document.cookie = `${PROBE}=1; domain=${domain}; path=/; SameSite=Lax`;
        if (document.cookie.split('; ').includes(`${PROBE}=1`)) {
            document.cookie = `${PROBE}=; domain=${domain}; path=/; max-age=0`;

            return domain;
        }
    }

    return location.hostname;
};

const launch = () => {
    if (!CookieConsent.getUserPreferences().acceptedCategories.includes(ANALYTICS)) {
        return;
    }
    for (const counter of config.counters) {
        if (!started.has(counter.entry)) {
            started.add(counter.entry);
            import(counter.entry).then(module => module.default(counter.settings));
        }
    }
};

CookieConsent.run({
    mode: 'opt-out',
    cookie: {domain: registrable()},
    hideFromBots: true,
    guiOptions: {consentModal: {layout: 'cloud inline', position: 'bottom center'}},
    categories: {
        [NECESSARY]: {enabled: true, readOnly: true},
        [ANALYTICS]: {enabled: true, autoClear: {cookies: config.counters.map(counter => ({name: new RegExp(counter.cookies)})), reloadPage: true}},
    },
    onConsent: launch,
    onChange: launch,
    language: {
        default: config.language,
        translations: {
            [config.language]: {
                consentModal: {
                    label: texts.title,
                    description: texts.description,
                    acceptAllBtn: texts.accept,
                },
                preferencesModal: {
                    title: texts.settings,
                    acceptAllBtn: texts.acceptAll,
                    acceptNecessaryBtn: texts.acceptNecessary,
                    savePreferencesBtn: texts.save,
                    closeIconLabel: texts.close,
                    sections: [
                        {title: texts.necessary, description: texts.necessaryDescription, linkedCategory: NECESSARY},
                        {title: texts.analytics, description: texts.analyticsDescription, linkedCategory: ANALYTICS},
                    ],
                },
            },
        },
    },
}).then(launch);

document.addEventListener('click', event => {
    if (event.target.closest('[data-consent-open]')) {
        CookieConsent.showPreferences();
    }
});
