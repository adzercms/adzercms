export default ({tag, measurement, consent}) => {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () {
        window.dataLayer.push(arguments);
    };

    window.gtag('consent', 'default', consent);
    window.gtag('js', new Date());
    window.gtag('config', measurement);

    const script = document.createElement('script');
    script.async = true;
    script.src = tag;
    document.head.append(script);
};
