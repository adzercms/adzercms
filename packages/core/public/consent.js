import * as CookieConsent from 'vanilla-cookieconsent';

const NECESSARY = 'necessary';
const ANALYTICS = 'analytics';
const PROBE = 'adzercms_probe';

const config = JSON.parse(document.querySelector('script[data-consent]').dataset.consent);
const texts = config.texts;

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
        import(counter.entry).then(module => module.default(counter.settings));
    }
};

CookieConsent.run({
    mode: 'opt-out',
    cookie: {domain: registrable()},
    hideFromBots: true,
    guiOptions: {consentModal: {layout: 'cloud inline', position: 'bottom center'}},
    categories: {
        [NECESSARY]: {enabled: true, readOnly: true},
        [ANALYTICS]: {enabled: true},
    },
    language: {
        default: config.language,
        translations: {
            [config.language]: {
                consentModal: {
                    label: texts.title,
                    description: texts.description,
                    acceptAllBtn: texts.accept,
                },
            },
        },
    },
}).then(launch);
